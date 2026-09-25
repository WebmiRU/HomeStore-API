<?php

namespace Tests\Unit;

use App\Models\LabelPreset;
use PHPUnit\Framework\TestCase;

/**
 * Раскладка шаблона задаёт, сколько кодов попадёт в набор безымянных
 * этикеток: ровно столько, сколько ячеек помещается на лист. Ошибка на
 * единицу здесь означает либо лишнюю наклейку, либо пустую ячейку на листе.
 */
class LabelPresetLayoutTest extends TestCase
{
    private function mini(
        int|float $cellWidth = 16,
        int|float $cellHeight = 16,
        int|float $margin = 10,
    ): LabelPreset {
        // Системный мини-шаблон: A4, поля 10 мм, ячейка 12 мм.
        return new LabelPreset([
            'page_width'         => 210,
            'page_height'        => 297,
            'page_margin_top'    => $margin,
            'page_margin_right'  => $margin,
            'page_margin_bottom' => $margin,
            'page_margin_left'   => $margin,
            'cell_width'         => $cellWidth,
            'cell_height'        => $cellHeight,
        ]);
    }

    public function test_system_preset_fits_187_labels(): void
    {
        $this->assertSame(
            ['columns' => 11, 'rows' => 17, 'per_page' => 187],
            $this->mini()->layout()
        );
    }

    public function test_layout_ignores_the_remainder(): void
    {
        // 277 доступной высоты / 16 = 17,31 — вниз уходит 17 рядов,
        // остаток в 5 мм пустым остаётся, а не уходит в 18-й ряд.
        $layout = $this->mini()->layout();

        $this->assertSame(17, $layout['rows']);
        $this->assertLessThan(16, (297 - 20) - $layout['rows'] * 16);
    }

    public function test_per_page_is_the_product(): void
    {
        $layout = $this->mini(25, 50)->layout();

        $this->assertSame(7, $layout['columns']);
        $this->assertSame(5, $layout['rows']);
        $this->assertSame(35, $layout['per_page']);
    }

    public function test_margins_reduce_the_available_area(): void
    {
        // Ячейка 25 мм одна и та же, различаются только поля:
        // 190 мм доступно -> 7 ячеек, 150 мм -> 6.
        $this->assertSame(7, $this->mini(25, 50, 10)->layout()['columns']);
        $this->assertSame(6, $this->mini(25, 50, 30)->layout()['columns']);
    }

    public function test_cell_larger_than_the_sheet_yields_one_cell(): void
    {
        // Не ноль: иначе набор создался бы пустым, а такая геометрия
        // бессмысленна.
        $layout = $this->mini(500, 500)->layout();

        $this->assertSame(1, $layout['columns']);
        $this->assertSame(1, $layout['rows']);
        $this->assertSame(1, $layout['per_page']);
    }
}
