<?php

namespace App\Services\Tasks;

use Carbon\CarbonImmutable;

final class QuickAdd
{
    /** Extrae metadatos y conserva los tokens desconocidos en el título. */
    public static function parse(string $input, string $hoy, array $areas): array
    {
        $result = ['titulo' => '', 'etiquetas' => []];
        $today = CarbonImmutable::createFromFormat('!Y-m-d', $hoy);
        $input = preg_replace_callback('/\[\[([^\[\]\r\n]+)\]\]/u', function (array $match) use (&$result): string {
            if (trim($match[1]) === '') {
                return $match[0];
            }
            $result['proyecto'] = $match[0];

            return ' ';
        }, $input);
        $title = [];
        foreach (preg_split('/\s+/u', trim($input), -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $normalized = mb_strtolower($token);
            $priorities = ['!alta' => 'alta', '!media' => 'media', '!baja' => 'baja', '!1' => 'alta', '!2' => 'media', '!3' => 'baja'];
            if (isset($priorities[$normalized])) {
                $result['prioridad'] = $priorities[$normalized];

                continue;
            }
            if (preg_match('/\A#([\p{L}\p{N}_-]+)\z/u', $normalized, $matches)) {
                if (in_array($matches[1], $areas, true)) {
                    $result['area'] = $matches[1];
                } elseif (! in_array($matches[1], $result['etiquetas'], true)) {
                    $result['etiquetas'][] = $matches[1];
                }

                continue;
            }
            if (str_starts_with($normalized, '@')) {
                $date = self::dueDate(substr($normalized, 1), $today);
                if ($date !== null) {
                    $result['vence'] = $date->format('Y-m-d');

                    continue;
                }
            }
            if (str_starts_with($token, '*')) {
                $recurrence = Recurrence::parse(substr($token, 1));
                if ($recurrence !== null) {
                    $result['repite'] = $recurrence->rule();

                    continue;
                }
            }
            $title[] = $token;
        }
        $result['titulo'] = implode(' ', $title);
        if (isset($result['repite']) && ! isset($result['vence'])) {
            $result['vence'] = Recurrence::parse($result['repite'])->next($today->subDay()->format('Y-m-d'));
        }

        return $result;
    }

    private static function dueDate(string $token, CarbonImmutable $today): ?CarbonImmutable
    {
        $relative = ['hoy' => 0, 'mañana' => 1, 'manana' => 1, 'pasado' => 2];
        if (isset($relative[$token])) {
            return $today->addDays($relative[$token]);
        }
        $weekday = array_search(strtr($token, ['mié' => 'mie', 'sáb' => 'sab']), ['lun', 'mar', 'mie', 'jue', 'vie', 'sab', 'dom'], true);
        if ($weekday !== false) {
            $days = ($weekday + 1 - $today->dayOfWeekIso + 7) % 7;

            return $today->addDays($days === 0 ? 7 : $days);
        }
        if (preg_match('/\A([0-9]{1,2})\z/', $token, $matches)) {
            $day = (int) $matches[1];
            if ($day < 1 || $day > 31) {
                return null;
            }
            $month = $today->startOfMonth();
            $candidate = $month->day(min($day, $month->daysInMonth));
            if ($candidate->lessThanOrEqualTo($today)) {
                $month = $month->addMonth();
                $candidate = $month->day(min($day, $month->daysInMonth));
            }

            return $candidate;
        }
        if (preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $token, $matches)) {
            return self::calendarDate((int) $matches[1], (int) $matches[2], (int) $matches[3], $today);
        }
        if (preg_match('/\A([0-9]{1,2})\/([0-9]{1,2})(?:\/([0-9]{4}))?\z/', $token, $matches)) {
            $year = isset($matches[3]) ? (int) $matches[3] : $today->year;
            $date = self::calendarDate($year, (int) $matches[2], (int) $matches[1], $today);
            if ($date !== null && ! isset($matches[3]) && $date->lessThan($today)) {
                $date = self::calendarDate($year + 1, (int) $matches[2], (int) $matches[1], $today);
            }

            return $date;
        }
        if (preg_match('/\A\+([0-9]+)([sm]?)\z/', $token, $matches)) {
            // Limita el entero antes de calcular fechas fuera del calendario.
            if ((float) $matches[1] > 3652059) {
                return null;
            }
            $number = (int) $matches[1];
            $date = match ($matches[2]) {
                's' => $today->addWeeks($number),
                'm' => $today->addMonthsNoOverflow($number),
                default => $today->addDays($number),
            };

            return $date->year >= 1 && $date->year <= 9999 ? $date : null;
        }

        return null;
    }

    private static function calendarDate(int $year, int $month, int $day, CarbonImmutable $today): ?CarbonImmutable
    {
        return checkdate($month, $day, $year) ? $today->setDate($year, $month, $day) : null;
    }
}
