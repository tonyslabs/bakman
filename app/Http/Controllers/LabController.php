<?php

namespace App\Http\Controllers;

use App\Models\LabProject;
use App\Services\ServiceDiscovery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LabController extends Controller
{
    public const MODULES = [
        'develop' => 'Develop',
        'homelab' => 'Homelab',
    ];

    public function show(Request $request, string $module, ServiceDiscovery $discovery)
    {
        abort_unless(array_key_exists($module, self::MODULES), 404);

        $all = LabProject::where('module', $module)->orderBy('created_at')->get();
        $showHidden = $request->boolean('ocultos');
        $projects = $showHidden ? $all : $all->where('hidden', false)->values();

        return view('lab.show', [
            'module' => $module,
            'moduleLabel' => self::MODULES[$module],
            'projects' => $projects,
            'hiddenCount' => $all->where('hidden', true)->count(),
            'showHidden' => $showHidden,
            'discovery' => $module === ServiceDiscovery::MODULE ? [
                'configured' => $discovery->configured(),
                'last' => $discovery->lastSync(),
            ] : null,
            // Secciones en el orden en que aparecieron por primera vez; las sin sección al final.
            'sections' => $projects->groupBy(fn ($p) => $p->section ?: '')->sortBy(fn ($items, $section) => $section === '' ? 1 : 0),
        ]);
    }

    public function create(string $module)
    {
        abort_unless(array_key_exists($module, self::MODULES), 404);

        return view('lab.form', [
            'module' => $module,
            'moduleLabel' => self::MODULES[$module],
            'project' => new LabProject(['module' => $module, 'scheme' => 'http']),
            'sections' => $this->sections($module),
        ]);
    }

    public function store(Request $request, string $module)
    {
        abort_unless(array_key_exists($module, self::MODULES), 404);

        $data = $this->validated($request, $module);
        $data['module'] = $module;
        $data['monitor_only'] = $request->boolean('monitor_only');
        LabProject::create($data);

        return redirect()->route('lab.show', $module)->with('status', 'Proyecto creado.');
    }

    public function edit(LabProject $labProject)
    {
        return view('lab.form', [
            'module' => $labProject->module,
            'moduleLabel' => self::MODULES[$labProject->module] ?? $labProject->module,
            'project' => $labProject,
            'sections' => $this->sections($labProject->module),
        ]);
    }

    public function update(Request $request, LabProject $labProject)
    {
        $labProject->update($this->validated($request, $labProject->module) + ['monitor_only' => $request->boolean('monitor_only')]);

        return redirect()->route('lab.show', $labProject->module)->with('status', 'Proyecto actualizado.');
    }

    public function destroy(LabProject $labProject)
    {
        $module = $labProject->module;
        $labProject->delete();

        return redirect()->route('lab.show', $module)->with('status', 'Proyecto eliminado.');
    }

    /**
     * ¿Responde el servicio? Desde el servidor, así funciona igual para hosts de
     * la tailnet y dominios públicos. Cualquier respuesta HTTP (incluidos 3xx/401/403)
     * cuenta como en línea: el servicio está vivo aunque pida login. Timeout de 10 s
     * porque algunos (Nextcloud) tardan ~10 s en la primera petición en frío.
     */
    public function ping(LabProject $labProject)
    {
        if ($labProject->isLoopback()) {
            return response()->json(['up' => null, 'status' => null, 'ms' => null]);
        }

        // Pedirse a sí mismo por HTTP es inútil: si esto responde, está arriba.
        $self = parse_url(config('app.url'));
        if (strtolower($labProject->host) === strtolower($self['host'] ?? '') && (int) $labProject->port === (int) ($self['port'] ?? 80)) {
            return response()->json(['up' => true, 'status' => 200, 'ms' => 0]);
        }

        $result = Cache::remember('lab.ping.'.$labProject->id, 30, function () use ($labProject) {
            $started = microtime(true);

            try {
                $response = Http::withoutVerifying()->withoutRedirecting()->connectTimeout(3)->timeout(10)->get($labProject->url);

                return ['up' => $response->status() < 500, 'status' => $response->status(), 'ms' => (int) round((microtime(true) - $started) * 1000)];
            } catch (\Throwable $e) {
                return ['up' => false, 'status' => null, 'ms' => null];
            }
        });

        return response()->json($result);
    }

    public function discover(ServiceDiscovery $discovery)
    {
        abort_unless($discovery->configured(), 422, 'Falta PORTAINER_TOKEN.');

        try {
            $s = $discovery->sync();
        } catch (\Throwable $e) {
            return redirect()->route('lab.show', ServiceDiscovery::MODULE)->with('status', 'No se pudo sincronizar con Portainer: '.Str::limit($e->getMessage(), 200));
        }

        return redirect()->route('lab.show', ServiceDiscovery::MODULE)->with('status', "Sincronizado: {$s['endpoints']} máquinas, {$s['created']} servicios nuevos, {$s['updated']} actualizados, {$s['removed']} ya no existen.");
    }

    public function toggleHidden(LabProject $labProject)
    {
        $labProject->update(['hidden' => ! $labProject->hidden]);

        return redirect()->back(fallback: route('lab.show', $labProject->module))
            ->with('status', $labProject->hidden ? "{$labProject->name} oculto." : "{$labProject->name} visible otra vez.");
    }

    private function sections(string $module): array
    {
        return LabProject::where('module', $module)->whereNotNull('section')->distinct()->orderBy('section')->pluck('section')->all();
    }

    private function validated(Request $request, string $module): array
    {
        return $request->validate([
            'section' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'scheme' => ['required', 'in:http,https'],
            'host' => ['required', 'string', 'max:255'],
            'port' => [$module === 'develop' ? 'required' : 'nullable', 'integer', 'min:1', 'max:65535'],
            'path' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'container' => ['nullable', 'string', 'max:150'],
            'monitor_only' => ['sometimes', 'boolean'],
        ]);
    }
}
