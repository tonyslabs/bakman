<?php

namespace App\Services;

use App\Models\LabProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Crea y actualiza las tarjetas de Lab → Homelab a partir de los contenedores
 * que Portainer ve en cada máquina. Nunca borra tarjetas: las que ya no tienen
 * contenedor quedan marcadas como "removed" y las ocultas siguen ocultas.
 */
class ServiceDiscovery
{
    public const MODULE = 'homelab';

    public function configured(): bool
    {
        return filled(config('lab.portainer.token'));
    }

    /** @return array{created: int, updated: int, removed: int, endpoints: int} */
    public function sync(): array
    {
        // Al segundo: MariaDB guarda last_seen_at sin microsegundos y la comparación
        // "< $now" de abajo marcaría como borradas las tarjetas recién vistas.
        $now = now()->startOfSecond();
        $stats = ['created' => 0, 'updated' => 0, 'removed' => 0, 'endpoints' => 0];
        $synced = [];

        foreach ($this->api('/api/endpoints') as $endpoint) {
            $host = $this->endpointHost($endpoint);
            $section = config('lab.sections_by_host')[$host] ?? $endpoint['Name'];

            // Status 2 = el agente no responde: no tocar sus tarjetas (no están "borradas").
            if (($endpoint['Status'] ?? 1) !== 1) {
                continue;
            }

            $stats['endpoints']++;
            $synced[] = $section;

            foreach ($this->api("/api/endpoints/{$endpoint['Id']}/docker/containers/json", ['all' => 1]) as $container) {
                $name = ltrim($container['Names'][0] ?? '', '/');
                if ($name === '') {
                    continue;
                }

                $state = [
                    'container_state' => $container['State'] ?? null,
                    'container_health' => $this->health($container['Status'] ?? ''),
                    'container_status' => Str::limit($container['Status'] ?? '', 140, ''),
                    'last_seen_at' => $now,
                ];

                $card = LabProject::where('module', self::MODULE)->where('section', $section)->where('container', $name)->first();

                if ($card) {
                    $card->update($state);
                    $stats['updated']++;

                    continue;
                }

                [$scheme, $port] = $this->webPort($container['Ports'] ?? []);

                LabProject::create($state + [
                    'module' => self::MODULE,
                    'section' => $section,
                    'name' => $this->displayName($container, $name),
                    'scheme' => $scheme,
                    'host' => $host,
                    'port' => $port,
                    'description' => $container['Image'] ?? null,
                    'monitor_only' => $port === null,
                    'container' => $name,
                    'discovered' => true,
                ]);
                $stats['created']++;
            }
        }

        // Tarjetas con contenedor en máquinas sincronizadas que ya no aparecieron.
        $stats['removed'] = LabProject::where('module', self::MODULE)
            ->whereIn('section', $synced)
            ->whereNotNull('container')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $now))
            ->where(fn ($q) => $q->whereNull('container_state')->orWhere('container_state', '!=', 'removed'))
            ->update(['container_state' => 'removed', 'container_health' => null, 'container_status' => null]);

        Cache::forever('lab.discovery.last', ['at' => $now->toIso8601String()] + $stats);

        return $stats;
    }

    public function lastSync(): ?array
    {
        $last = Cache::get('lab.discovery.last');

        return $last ? ['at' => Carbon::parse($last['at'])] + $last : null;
    }

    private function api(string $path, array $query = []): array
    {
        return Http::baseUrl(rtrim(config('lab.portainer.url'), '/'))
            ->withHeaders(['X-API-Key' => config('lab.portainer.token')])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->get($path, $query)
            ->throw()
            ->json() ?? [];
    }

    /** tcp://100.99.131.25:9001 → 100.99.131.25; el socket local → la IP del homelab. */
    private function endpointHost(array $endpoint): string
    {
        $host = parse_url($endpoint['URL'] ?? '', PHP_URL_HOST);

        return $host && ! str_starts_with($endpoint['URL'], 'unix://') ? $host : config('lab.local_host');
    }

    /**
     * Puerto publicado alcanzable desde fuera de la máquina (no 127.0.0.1/::1).
     * Prefiere el menor puerto HTTP, para no depender de certificados (Portainer
     * 9000/9443 → 9000); si solo publica HTTPS, usa ese.
     *
     * @return array{0: string, 1: int|null}
     */
    private function webPort(array $ports): array
    {
        $candidates = collect($ports)
            ->filter(fn ($p) => ($p['Type'] ?? 'tcp') === 'tcp' && isset($p['PublicPort']))
            ->reject(fn ($p) => in_array($p['IP'] ?? '', ['127.0.0.1', '::1'], true))
            ->reject(fn ($p) => in_array($p['PrivatePort'], config('lab.non_web_ports'), true))
            ->sortBy('PublicPort')
            ->values();

        if ($candidates->isEmpty()) {
            return ['http', null];
        }

        $http = $candidates->first(fn ($p) => ! in_array($p['PrivatePort'], config('lab.https_ports'), true));
        $chosen = $http ?? $candidates->first();

        return [in_array($chosen['PrivatePort'], config('lab.https_ports'), true) ? 'https' : 'http', (int) $chosen['PublicPort']];
    }

    private function health(string $status): ?string
    {
        return match (true) {
            str_contains($status, '(healthy)') => 'healthy',
            str_contains($status, '(unhealthy)') => 'unhealthy',
            str_contains($status, '(health: starting)') => 'starting',
            default => null,
        };
    }

    /** Nombre legible: el servicio de compose si existe (vikunja-vikunja-1 → Vikunja). */
    private function displayName(array $container, string $name): string
    {
        $service = $container['Labels']['com.docker.compose.service'] ?? $name;

        if ($known = config('lab.display_names')[strtolower($service)] ?? null) {
            return $known;
        }

        return Str::of($service)->replace(['_', '-'], ' ')->title()->toString();
    }
}
