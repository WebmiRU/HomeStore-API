<?php

namespace Tests\Unit;

use App\Support\CodeFormat;
use PHPUnit\Framework\TestCase;

/**
 * Поиск кода в двух написаниях нужен списанию по коду.
 *
 * Код предмета ищется по обоим вариантам записи: исторические коды хранятся
 * с дефисами, коды из наборов безымянных этикеток — без, а предмет мог быть
 * создан в одно время и отсканирован в другое. Если бы candidates() отдавал
 * только исходное написание, списание по коду не нашло бы свой же предмет и
 * тихо списало бы количество, оставив код занятым.
 */
class WriteoffCodeCandidatesTest extends TestCase
{
    public function test_bare_and_dashed_spellings_are_both_tried(): void
    {
        $dashed = '01a0d9ac-6762-723d-ac1c-a3ac5775be38';
        $bare   = '01a0d9ac6762723dac1ca3ac5775be38';

        $this->assertContains($dashed, CodeFormat::candidates($dashed));
        $this->assertContains($bare, CodeFormat::candidates($dashed));
        $this->assertContains($dashed, CodeFormat::candidates($bare));
        $this->assertContains($bare, CodeFormat::candidates($bare));
    }

    public function test_a_code_that_is_not_uuid_keeps_its_own_spelling(): void
    {
        // Штрихкод из набора безымянных этикеток — не UUID, и дефисов в нём
        // нет: добавлять варианты нечего, а ломать сам код нельзя.
        $candidates = CodeFormat::candidates('2202515016011');

        $this->assertContains('2202515016011', $candidates);
    }
}
