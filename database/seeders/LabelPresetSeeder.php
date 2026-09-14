<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LabelPresetSeeder extends Seeder
{
    public function run(): void
    {
        // Roboto Condensed Bold не входит в сгенерированные TCPDF-шрифты (tc-lib-pdf-font),
        // поэтому используем ближайший доступный аналог с поддержкой кириллицы.
        $fontId = DB::table('font')->where('key', 'dejavusanscondensedb')->value('id');

        $presets = [
            [
                'title'               => 'Лоток 1л',
                'page_width'          => 210.0,
                'page_height'         => 297.0,
                'page_margin_top'     => 10.0,
                'page_margin_right'   => 10.0,
                'page_margin_bottom'  => 10.0,
                'page_margin_left'    => 10.0,
                'cell_width'          => 77.0,
                'cell_height'         => 22.0,
                'cell_pad_top'        => 3.0,
                'cell_pad_right'      => 6.0,
                'cell_pad_bottom'     => 5.0,
                'cell_pad_left'       => 6.0,
                'barcode_position'    => 'left',
                'barcode_text_gap'    => 2.0,
                'barcode_size'        => 13.0,
                'font_id'             => $fontId,
                'font_size_min'       => 5.0,
                'font_size_max'       => 24.0,
                'font_size_step'      => 0.5,
                'line_height_factor'  => 1.25,
            ],
            [
                'title'               => 'Хранилища',
                'page_width'          => 210.0,
                'page_height'         => 297.0,
                'page_margin_top'     => 10.0,
                'page_margin_right'   => 10.0,
                'page_margin_bottom'  => 10.0,
                'page_margin_left'    => 10.0,
                'cell_width'          => 95.0,
                'cell_height'         => 20.0,
                'cell_pad_top'        => 2.0,
                'cell_pad_right'      => 2.0,
                'cell_pad_bottom'     => 2.0,
                'cell_pad_left'       => 2.0,
                'barcode_position'    => 'left',
                'barcode_text_gap'    => 2.0,
                'barcode_size'        => 13.0,
                'font_id'             => $fontId,
                'font_size_min'       => 5.0,
                'font_size_max'       => 24.0,
                'font_size_step'      => 0.5,
                'line_height_factor'  => 1.25,
            ],
        ];

        foreach ($presets as $preset) {
            DB::table('label_preset')->updateOrInsert(
                ['title' => $preset['title']],
                $preset + ['updated_at' => now()]
            );
        }
    }
}
