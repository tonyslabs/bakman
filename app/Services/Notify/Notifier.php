<?php

namespace App\Services\Notify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publica avisos en ntfy (JSON a la raíz del servidor). Nunca lanza: un aviso que no sale no
 * puede romper un backup ni el scheduler; queda en el log.
 */
class Notifier
{
    public function enabled(): bool
    {
        return filled(config('notify.ntfy.url')) && filled(config('notify.ntfy.token'));
    }

    /**
     * @param  string  $topic  clave de config('notify.topics') (tareas, jobs) o nombre de topic
     * @param  array{priority?: int, tags?: list<string>, click?: string, actions?: list<array>}  $options
     */
    public function send(string $topic, string $title, string $message, array $options = []): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $payload = array_filter([
            'topic' => config("notify.topics.{$topic}", $topic),
            'title' => $title,
            'message' => $message,
            'priority' => $options['priority'] ?? 3,
            'tags' => $options['tags'] ?? null,
            'click' => $options['click'] ?? null,
            'actions' => $options['actions'] ?? null,
            'markdown' => $options['markdown'] ?? null,
        ], fn ($v) => $v !== null);

        try {
            $response = Http::withToken(config('notify.ntfy.token'))
                ->timeout(config('notify.ntfy.timeout', 5))
                ->acceptJson()
                ->post(rtrim(config('notify.ntfy.url'), '/'), $payload);

            if ($response->failed()) {
                Log::warning('ntfy: no se pudo enviar el aviso', ['status' => $response->status(), 'body' => $response->body(), 'title' => $title]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('ntfy: no se pudo enviar el aviso', ['error' => $e->getMessage(), 'title' => $title]);

            return false;
        }
    }

    /** Link a una ruta de bakman con la URL de la tailnet (el teléfono no ve la red `core`). */
    public static function link(string $path): string
    {
        return rtrim(config('notify.app_url'), '/').'/'.ltrim($path, '/');
    }

    /** Acción "view" de ntfy (botón en la notificación). */
    public static function view(string $label, string $url): array
    {
        return ['action' => 'view', 'label' => $label, 'url' => $url, 'clear' => true];
    }
}
