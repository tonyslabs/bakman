<?php

namespace App\Services\Tasks;

use Carbon\CarbonImmutable;

final class Recurrence
{
    private const array DAYS = ['lun', 'mar', 'mie', 'jue', 'vie', 'sab', 'dom'];

    private function __construct(private readonly string $canonical) {}

    public static function parse(?string $rule): ?self
    {
        if ($rule === null || $rule === '') {
            return null;
        }
        $rule = strtr(mb_strtolower($rule), ['mié' => 'mie', 'sáb' => 'sab']);
        if (in_array($rule, ['diaria', 'habiles', 'semanal', 'mensual', 'mensual:ultimo', 'anual'], true)) {
            return new self($rule);
        }
        if (preg_match('/\Asemanal:((?:lun|mar|mie|jue|vie|sab|dom)(?:, *(?:lun|mar|mie|jue|vie|sab|dom))*)\z/', $rule, $matches)) {
            $days = explode(',', str_replace(' ', '', $matches[1]));

            return new self('semanal:'.implode(',', array_intersect(self::DAYS, $days)));
        }
        if (preg_match('/\Amensual:([0-9]+)\z/', $rule, $matches) && (int) $matches[1] >= 1 && (int) $matches[1] <= 31) {
            return new self('mensual:'.(int) $matches[1]);
        }
        if (preg_match('/\Acada:([0-9]+)([dsm])\z/', $rule, $matches) && (int) $matches[1] >= 1 && (int) $matches[1] <= 365) {
            return new self('cada:'.(int) $matches[1].$matches[2]);
        }

        return null;
    }

    public function rule(): string
    {
        return $this->canonical;
    }

    public function describe(): string
    {
        $simple = ['diaria' => 'Cada día', 'habiles' => 'Días hábiles', 'semanal' => 'Cada semana', 'mensual' => 'Cada mes', 'mensual:ultimo' => 'Cada mes, último día', 'anual' => 'Cada año'];
        if (isset($simple[$this->canonical])) {
            return $simple[$this->canonical];
        }
        [$kind, $value] = explode(':', $this->canonical);
        if ($kind === 'semanal') {
            $days = explode(',', strtr($value, ['mie' => 'mié', 'sab' => 'sáb']));
            $last = array_pop($days);

            return 'Cada semana: '.($days === [] ? $last : implode(', ', $days).' y '.$last);
        }
        if ($kind === 'mensual') {
            return 'Cada mes, día '.$value;
        }
        $number = (int) $value;
        [$singular, $plural] = match (substr($value, -1)) {
            'd' => ['día', 'días'], 's' => ['semana', 'semanas'], 'm' => ['mes', 'meses'],
        };

        return $number === 1 ? 'Cada '.$singular : 'Cada '.$number.' '.$plural;
    }

    public function next(string $from): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $from);

        return $this->advance($date, $date)->format('Y-m-d');
    }

    public static function nextOccurrence(string $rule, ?string $vence, string $completada, string $hoy, string $desde = 'vence'): ?string
    {
        $recurrence = self::parse($rule);
        if ($recurrence === null) {
            return null;
        }
        $base = ($desde === 'completada' || $vence === null) ? $completada : $vence;
        $anchor = CarbonImmutable::createFromFormat('!Y-m-d', $base);
        $next = $recurrence->advance($anchor, $anchor);
        while ($next->format('Y-m-d') < $hoy) {
            $next = $recurrence->advance($next, $anchor);
        }

        return $next->format('Y-m-d');
    }

    private function advance(CarbonImmutable $from, CarbonImmutable $anchor): CarbonImmutable
    {
        if ($this->canonical === 'diaria') {
            return $from->addDay();
        }
        if ($this->canonical === 'habiles' || str_starts_with($this->canonical, 'semanal:')) {
            $days = $this->canonical === 'habiles' ? array_slice(self::DAYS, 0, 5) : explode(',', substr($this->canonical, 8));
            do {
                $from = $from->addDay();
            } while (! in_array(self::DAYS[$from->dayOfWeekIso - 1], $days, true));

            return $from;
        }
        if ($this->canonical === 'semanal') {
            return $from->addWeek();
        }
        if ($this->canonical === 'anual') {
            // Conserva el 29 de febrero para futuros años bisiestos.
            $month = $from->addYear()->startOfYear()->addMonths($anchor->month - 1);

            return $month->day(min($anchor->day, $month->daysInMonth));
        }
        if ($this->canonical === 'mensual' || str_starts_with($this->canonical, 'mensual:')) {
            $value = explode(':', $this->canonical)[1] ?? null;
            $day = $value === null ? $anchor->day : ($value === 'ultimo' ? 31 : (int) $value);
            $month = $from->startOfMonth();
            $candidate = $month->day(min($day, $month->daysInMonth));
            if ($candidate->lessThanOrEqualTo($from)) {
                $month = $month->addMonth();
                $candidate = $month->day(min($day, $month->daysInMonth));
            }

            return $candidate;
        }
        $value = substr($this->canonical, 5);
        $number = (int) $value;

        return match (substr($value, -1)) {
            'd' => $from->addDays($number),
            's' => $from->addWeeks($number),
            'm' => $from->addMonthsNoOverflow($number),
        };
    }
}
