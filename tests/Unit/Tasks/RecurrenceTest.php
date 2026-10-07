<?php

namespace Tests\Unit\Tasks;

use App\Services\Tasks\Recurrence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecurrenceTest extends TestCase
{
    #[DataProvider('rules')]
    public function test_normalizes_and_describes_rules(string $input, string $canonical, string $description): void
    {
        $rule = Recurrence::parse($input);
        self::assertNotNull($rule);
        self::assertSame($canonical, $rule->rule());
        self::assertSame($description, $rule->describe());
    }

    public static function rules(): array
    {
        return [
            ['DIARIA', 'diaria', 'Cada día'], ['HABILES', 'habiles', 'Días hábiles'],
            ['semanal', 'semanal', 'Cada semana'],
            ['semanal:JUE, lun,lun', 'semanal:lun,jue', 'Cada semana: lun y jue'],
            ['semanal:vie,mié,lun', 'semanal:lun,mie,vie', 'Cada semana: lun, mié y vie'],
            ['semanal:SÁB', 'semanal:sab', 'Cada semana: sáb'],
            ['mensual', 'mensual', 'Cada mes'], ['mensual:15', 'mensual:15', 'Cada mes, día 15'],
            ['MENSUAL:ULTIMO', 'mensual:ultimo', 'Cada mes, último día'], ['anual', 'anual', 'Cada año'],
            ['cada:3d', 'cada:3d', 'Cada 3 días'], ['cada:2s', 'cada:2s', 'Cada 2 semanas'],
            ['cada:6m', 'cada:6m', 'Cada 6 meses'], ['cada:1d', 'cada:1d', 'Cada día'],
            ['cada:1s', 'cada:1s', 'Cada semana'], ['cada:1m', 'cada:1m', 'Cada mes'],
            ['CADA:365D', 'cada:365d', 'Cada 365 días'],
        ];
    }

    #[DataProvider('invalidRules')]
    public function test_rejects_invalid_rules(?string $input): void
    {
        self::assertNull(Recurrence::parse($input));
        if ($input !== null) {
            self::assertNull(Recurrence::nextOccurrence($input, null, '2026-01-01', '2026-01-01'));
        }
    }

    public static function invalidRules(): array
    {
        return array_map(fn ($rule) => [$rule], [null, '', ' ', ' diaria', 'diaria ', 'daily', 'semanal:', 'semanal:foo', 'semanal:lun,', 'semanal:lun ,jue', 'semanal:lun,,jue', 'mensual:0', 'mensual:32', 'mensual:-1', 'mensual:1.5', 'cada:0d', 'cada:366d', 'cada:-3d', 'cada:3a', 'cada: 3d', 'anual:2', "diaria\n"]);
    }

    #[DataProvider('nextDates')]
    public function test_next_is_strictly_later(string $rule, string $from, string $expected): void
    {
        self::assertSame($expected, Recurrence::parse($rule)->next($from));
    }

    public static function nextDates(): array
    {
        return [
            ['diaria', '2026-12-31', '2027-01-01'], ['habiles', '2026-10-09', '2026-10-12'],
            ['habiles', '2026-10-10', '2026-10-12'], ['habiles', '2026-10-11', '2026-10-12'],
            ['habiles', '2026-10-12', '2026-10-13'], ['semanal', '2026-10-06', '2026-10-13'],
            ['semanal:lun,jue', '2026-10-08', '2026-10-12'], ['semanal:lun,jue', '2026-10-06', '2026-10-08'],
            ['semanal:dom', '2026-10-11', '2026-10-18'], ['mensual', '2026-01-31', '2026-02-28'],
            ['mensual', '2024-01-31', '2024-02-29'], ['mensual:15', '2026-10-01', '2026-10-15'],
            ['mensual:15', '2026-10-15', '2026-11-15'], ['mensual:31', '2026-02-01', '2026-02-28'],
            ['mensual:31', '2026-02-28', '2026-03-31'], ['mensual:ultimo', '2024-02-01', '2024-02-29'],
            ['mensual:ultimo', '2026-12-31', '2027-01-31'], ['anual', '2024-02-29', '2025-02-28'],
            ['anual', '2026-12-31', '2027-12-31'], ['cada:3d', '2026-10-30', '2026-11-02'],
            ['cada:2s', '2026-12-25', '2027-01-08'], ['cada:1m', '2026-01-31', '2026-02-28'],
            ['cada:6m', '2026-08-31', '2027-02-28'], ['cada:365d', '2024-01-01', '2024-12-31'],
        ];
    }

    #[DataProvider('occurrences')]
    public function test_completion_catches_up_and_preserves_original_anchor(string $rule, ?string $due, string $completed, string $today, string $since, string $expected): void
    {
        self::assertSame($expected, Recurrence::nextOccurrence($rule, $due, $completed, $today, $since));
    }

    public static function occurrences(): array
    {
        return [
            ['diaria', '2026-10-01', '2026-10-06', '2026-10-06', 'vence', '2026-10-06'],
            ['cada:3d', '2026-10-01', '2026-10-06', '2026-10-06', 'vence', '2026-10-07'],
            ['semanal:lun,jue', '2026-10-01', '2026-10-10', '2026-10-10', 'vence', '2026-10-12'],
            ['diaria', '2026-10-01', '2026-10-06', '2026-10-06', 'completada', '2026-10-07'],
            ['semanal', null, '2026-10-06', '2026-10-06', 'vence', '2026-10-13'],
            ['mensual', '2026-01-31', '2026-03-01', '2026-03-01', 'vence', '2026-03-31'],
            ['mensual', '2024-01-31', '2024-04-01', '2024-04-01', 'vence', '2024-04-30'],
            ['mensual', '2026-01-31', '2026-03-15', '2026-03-15', 'completada', '2026-04-15'],
            ['anual', '2024-02-29', '2028-02-29', '2028-02-29', 'vence', '2028-02-29'],
            ['anual', '2024-02-29', '2028-03-01', '2028-03-01', 'vence', '2029-02-28'],
            ['cada:1m', '2026-01-31', '2026-03-01', '2026-03-01', 'vence', '2026-03-28'],
            ['diaria', '2026-10-10', '2026-10-06', '2026-10-06', 'vence', '2026-10-11'],
        ];
    }
}
