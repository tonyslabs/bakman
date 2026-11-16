<?php

namespace App\Http\Controllers;

use App\Services\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function sshKey()
    {
        $pubKeyPath = config('backups.ssh_key_path').'.pub';
        $publicKey = file_exists($pubKeyPath) ? trim(file_get_contents($pubKeyPath)) : null;

        return view('settings.ssh-key', ['publicKey' => $publicKey]);
    }

    public function edit(AppSettings $settings)
    {
        $definitions = AppSettings::definitions();
        $stored = $settings->stored();

        return view('settings.edit', [
            'groups' => collect($definitions)->map(fn ($d, $key) => $d + [
                'key' => $key,
                'value' => $d['type'] === 'secret' ? '' : ($stored[$key] ?? ''),
                'default' => isset($stored[$key]) || $d['type'] === 'secret' ? '' : $settings->display($key),
                'has_secret' => $d['type'] === 'secret' && filled($stored[$key] ?? null),
                'overridden' => isset($stored[$key]),
            ])->groupBy('group'),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    /**
     * Texto vacío = quitar el ajuste y volver al valor del .env/por defecto. En los secretos,
     * vacío = dejar el que está; para quitarlo se marca "Quitar".
     */
    public function update(Request $request, AppSettings $settings)
    {
        $definitions = AppSettings::definitions();
        $input = $request->validate([
            'settings' => ['array'],
            'settings.general_timezone' => ['nullable', Rule::in(\DateTimeZone::listIdentifiers())],
            'clear' => ['array'],
        ])['settings'] ?? [];

        foreach ($definitions as $key => $definition) {
            $field = str_replace('.', '_', $key);
            $value = $request->input("settings.{$field}");

            if ($definition['type'] === 'secret') {
                if ($request->boolean("clear.{$field}")) {
                    $settings->set($key, null);
                } elseif (filled($value)) {
                    $settings->set($key, $value);
                }

                continue;
            }

            if ($definition['type'] === 'int' && filled($value) && (! ctype_digit((string) $value) || (int) $value < 1)) {
                return back()->withInput()->withErrors(["settings.{$field}" => 'Debe ser un número entero mayor que 0.']);
            }

            $settings->set($key, $value);
        }

        return redirect()->route('settings.edit')->with('status', 'Ajustes guardados.');
    }
}
