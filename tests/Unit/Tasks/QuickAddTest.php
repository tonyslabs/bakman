<?php

namespace Tests\Unit\Tasks;

use App\Services\Tasks\QuickAdd;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuickAddTest extends TestCase
{
    #[DataProvider('dates')]
    public function test_parses_dates(string $token, string $today, string $expected): void
    {
        self::assertSame(['titulo' => 'Hacer', 'etiquetas' => [], 'vence' => $expected], QuickAdd::parse('Hacer '.$token, $today, []));
    }

    public static function dates(): array
    {
        return [
            ['@hoy', '2026-10-06', '2026-10-06'], ['@mañana', '2026-12-31', '2027-01-01'],
            ['@manana', '2024-02-28', '2024-02-29'], ['@pasado', '2026-12-31', '2027-01-02'],
            ['@lun', '2026-10-06', '2026-10-12'], ['@mar', '2026-10-06', '2026-10-13'],
            ['@mie', '2026-10-06', '2026-10-07'], ['@jue', '2026-10-06', '2026-10-08'],
            ['@vie', '2026-10-06', '2026-10-09'], ['@sab', '2026-10-06', '2026-10-10'],
            ['@dom', '2026-10-06', '2026-10-11'], ['@15', '2026-10-06', '2026-10-15'],
            ['@15', '2026-10-15', '2026-11-15'], ['@31', '2026-01-31', '2026-02-28'],
            ['@31', '2024-02-28', '2024-02-29'], ['@31', '2026-02-28', '2026-03-31'],
            ['@2026-10-10', '2026-10-06', '2026-10-10'], ['@2024-02-29', '2026-10-06', '2024-02-29'],
            ['@10/10', '2026-10-06', '2026-10-10'], ['@10/10', '2026-10-10', '2026-10-10'],
            ['@10/10', '2026-10-11', '2027-10-10'], ['@10/10/2027', '2026-10-06', '2027-10-10'],
            ['@29/02', '2024-01-01', '2024-02-29'], ['@+3', '2026-12-30', '2027-01-02'],
            ['@+2s', '2026-10-06', '2026-10-20'], ['@+1m', '2024-01-31', '2024-02-29'],
            ['@+1m', '2026-01-31', '2026-02-28'], ['@+0', '2026-10-06', '2026-10-06'],
        ];
    }

    #[DataProvider('priorities')]
    public function test_parses_priorities(string $token, string $expected): void
    {
        self::assertSame(['titulo' => 'Tarea', 'etiquetas' => [], 'prioridad' => $expected], QuickAdd::parse('Tarea '.$token, '2026-10-06', []));
    }

    public static function priorities(): array
    {
        return [['!alta', 'alta'], ['!media', 'media'], ['!baja', 'baja'], ['!1', 'alta'], ['!2', 'media'], ['!3', 'baja']];
    }

    #[DataProvider('recurrences')]
    public function test_defaults_due_date_from_recurrence(string $rule, string $today, string $expected): void
    {
        $result = QuickAdd::parse('Tarea *'.$rule, $today, []);
        self::assertSame('Tarea', $result['titulo']);
        self::assertSame($rule, $result['repite']);
        self::assertSame($expected, $result['vence']);
    }

    public static function recurrences(): array
    {
        return [['diaria', '2026-10-06', '2026-10-06'], ['habiles', '2026-10-10', '2026-10-12'], ['semanal:lun,jue', '2026-10-08', '2026-10-08'], ['semanal:lun,jue', '2026-10-09', '2026-10-12'], ['mensual:5', '2026-10-06', '2026-11-05'], ['mensual:ultimo', '2026-02-28', '2026-02-28'], ['cada:3d', '2026-10-06', '2026-10-08']];
    }

    #[DataProvider('invalidTokens')]
    public function test_preserves_invalid_tokens(string $token): void
    {
        self::assertSame(['titulo' => 'Tarea '.$token, 'etiquetas' => []], QuickAdd::parse('Tarea '.$token, '2026-10-06', ['up']));
    }

    public static function invalidTokens(): array
    {
        return array_map(fn ($token) => [$token], ['@ayer', '@0', '@32', '@2026-02-29', '@2026-13-01', '@2026-04-31', '@31/04', '@29/02/2027', '@10/13', '@+2d', '@+-1', '@+2a', '@2026-1-01', '@10/10/27', '*cada:0d', '*mensual:32', '*semanal:foo', '!urgente', '!4', '#', '#foo!', '[[]]']);
    }

    public function test_extracts_a_mixture_and_keeps_explicit_due_date(): void
    {
        $result = QuickAdd::parse('  Preparar   [[Nota con espacios]] informe !1 #UP #Árbol_2 #árbol_2 #otro-tag @mañana *SEMANAL:JUE,lun  ', '2026-10-06', ['up', 'personal']);
        self::assertEquals(['titulo' => 'Preparar informe', 'etiquetas' => ['árbol_2', 'otro-tag'], 'proyecto' => '[[Nota con espacios]]', 'prioridad' => 'alta', 'area' => 'up', 'vence' => '2026-10-07', 'repite' => 'semanal:lun,jue'], $result);
    }

    public function test_handles_empty_input_plain_text_and_single_word_projects(): void
    {
        self::assertSame(['titulo' => '', 'etiquetas' => []], QuickAdd::parse('  ', '2026-10-06', []));
        self::assertSame(['titulo' => 'Texto con espacios', 'etiquetas' => []], QuickAdd::parse(" Texto\tcon\n espacios ", '2026-10-06', []));
        self::assertEquals(['titulo' => 'Leer', 'etiquetas' => [], 'proyecto' => '[[Nota]]'], QuickAdd::parse('Leer [[Nota]]', '2026-10-06', []));
    }

    public function test_last_scalar_token_wins_and_tags_keep_first_seen_order(): void
    {
        self::assertEquals(['titulo' => '', 'etiquetas' => ['uno', 'dos'], 'prioridad' => 'baja', 'area' => 'casa', 'vence' => '2026-10-07', 'repite' => 'diaria', 'proyecto' => '[[Dos]]'], QuickAdd::parse('!alta !3 #up #casa #uno #dos #UNO @hoy @mañana *anual *diaria [[Uno]] [[Dos]]', '2026-10-06', ['up', 'casa']));
    }

    public function test_accepts_single_digit_dates_and_accented_weekdays(): void
    {
        $areas = ['up', 'personal'];
        $this->assertSame('2027-03-05', QuickAdd::parse('Algo @5/3', '2026-10-06', $areas)['vence']);
        $this->assertSame('2026-10-07', QuickAdd::parse('Algo @mié', '2026-10-06', $areas)['vence']);
        $this->assertSame('2026-10-10', QuickAdd::parse('Algo @sáb', '2026-10-06', $areas)['vence']);
    }
}
