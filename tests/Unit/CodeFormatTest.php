<?php

namespace Tests\Unit;

use App\Support\CodeFormat;
use PHPUnit\Framework\TestCase;

/**
 * Код бывает в двух записях: с дефисами (исторические коды предметов и
 * хранилищ) и без (коды из наборов безымянных этикеток — так символ
 * DataMatrix на два модуля меньше). Сканер отдаёт ровто то, что напечатано,
 * поэтому поиск обязан понимать обе записи в любом направлении.
 */
class CodeFormatTest extends TestCase
{
    private const BARE    = '01a0d9ac6762723dac1ca3ac5775be38';
    private const DASHED  = '01a0d9ac-6762-723d-ac1c-a3ac5775be38';

    public function test_bare_input_yields_both_spellings(): void
    {
        $this->assertSame(
            [self::BARE, self::DASHED],
            CodeFormat::candidates(self::BARE)
        );
    }

    public function test_dashed_input_yields_both_spellings(): void
    {
        $this->assertSame(
            [self::DASHED, self::BARE],
            CodeFormat::candidates(self::DASHED)
        );
    }

    public function test_uppercase_is_normalized_to_lowercase(): void
    {
        $this->assertContains(
            strtolower(self::BARE),
            CodeFormat::candidates(strtoupper(self::BARE))
        );
    }

    public function test_surrounding_whitespace_is_ignored(): void
    {
        $this->assertSame(
            [self::BARE, self::DASHED],
            CodeFormat::candidates('  ' . self::BARE . "\n")
        );
    }

    public function test_non_uuid_is_returned_as_is(): void
    {
        // Произвольный штрихкод: разворачивать нечего, ищем ровно его.
        $this->assertSame(['ABC-123/XYZ'], CodeFormat::candidates('ABC-123/XYZ'));
    }

    public function test_lookalike_uuid_is_not_expanded(): void
    {
        // 32 hex, но с не-шестнадцатеричными символами — не UUID.
        $this->assertSame(['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz'], CodeFormat::candidates('zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz'));

        // Дефисы стоят не на тех местах.
        $this->assertSame(['01a0d9ac-6762-723dac1c-a3ac5775be38'], CodeFormat::candidates('01a0d9ac-6762-723dac1c-a3ac5775be38'));
    }

    public function test_wrong_length_dashed_uuid_is_not_expanded(): void
    {
        // Усечённый дефисный UUID не должен молча превращаться в 32 символа.
        $this->assertSame(['01a0d9ac-6762-723d-ac1c'], CodeFormat::candidates('01a0d9ac-6762-723d-ac1c'));
    }
}
