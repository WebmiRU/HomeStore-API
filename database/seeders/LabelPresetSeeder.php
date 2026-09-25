<?php

namespace Database\Seeders;

use App\Models\UserProfile;
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

        $users = UserProfile::whereIn('email', [
            'admin@admin.admin',
            'demo@demo.demo',
            'user@user.user',
        ])->get();

        foreach ($users as $user) {
            foreach ($presets as $preset) {
                DB::table('label_preset')->updateOrInsert(
                    ['user_id' => $user->id, 'title' => $preset['title']],
                    $preset + ['user_id' => $user->id, 'updated_at' => now()]
                );
            }
        }

        $this->seedSystemMini($fontId);
    }

    /**
     * Системный шаблон мини-этикеток: только код, без подписи.
     *
     * Общий для всех пользователей, но не редактируемый и не удаляемый —
     * на нём держится генерация наборов безымянных этикеток, и произвольная
     * правка геометрии сломала бы их.
     *
     * Геометрия: ячейка 16 мм, символ 20x20 модулей по 0,5 мм = 10 мм,
     * белое поле 3 мм с каждой стороны (6X при минимуме ISO 16022 в 2X).
     * Поле заложено с запасом сверх стандарта: наклейки режут руками, и
     * неровный срез иначе либо зацепит символ, либо срежет тихую зону.
     * На A4 с полями 10 мм помещается 11x17 = 187 этикеток.
     */
    private function seedSystemMini(int|string|null $fontId): void
    {
        $mini = [
            'title'              => 'Мини-этикетки 16×16 (без текста)',
            'page_width'         => 210.0,
            'page_height'        => 297.0,
            'page_margin_top'    => 10.0,
            'page_margin_right'  => 10.0,
            'page_margin_bottom' => 10.0,
            'page_margin_left'   => 10.0,
            'cell_width'         => 16.0,
            'cell_height'        => 16.0,
            'cell_pad_top'       => 3.0,
            'cell_pad_right'     => 3.0,
            'cell_pad_bottom'    => 3.0,
            'cell_pad_left'       => 3.0,
            'barcode_position'   => 'left',
            'barcode_text_gap'   => 0.0,
            'barcode_size'       => 10.0,
            'show_text'          => false,
            'font_id'            => $fontId,
            'font_size_min'      => 5.0,
            'font_size_max'      => 24.0,
            'font_size_step'     => 0.5,
            'line_height_factor' => 1.25,
            'is_system'          => true,
            'user_id'            => null,
        ];

        // Ищем по is_system, а не по названию: системный шаблон по
        // определению один, и название в него входит (12x12 -> 16x16).
        // Поиск по названию после переименования не нашёл бы старый шаблон
        // и завёл бы второй, а старый остался бы неуправляемым.
        $existing = DB::table('label_preset')->where('is_system', true)->first();

        if ($existing !== null) {
            DB::table('label_preset')
                ->where('id', $existing->id)
                ->update($mini + ['updated_at' => now()]);

            return;
        }

        DB::table('label_preset')->insert($mini + ['created_at' => now(), 'updated_at' => now()]);
    }
}