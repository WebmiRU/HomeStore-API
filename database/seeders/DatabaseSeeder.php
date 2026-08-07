<?php

namespace Database\Seeders;

use App\Models\Code;
use App\Models\Font;
use App\Models\Item;
use App\Models\LabelPreset;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $fonts = [
            // Core PDF fonts
            ['name' => 'Courier',              'key' => 'courier'],
            ['name' => 'Courier Bold',         'key' => 'courierb'],
            ['name' => 'Courier Bold Italic',  'key' => 'courierbi'],
            ['name' => 'Courier Italic',       'key' => 'courieri'],
            ['name' => 'Helvetica',            'key' => 'helvetica'],
            ['name' => 'Helvetica Bold',       'key' => 'helveticab'],
            ['name' => 'Helvetica Bold Italic','key' => 'helveticabi'],
            ['name' => 'Helvetica Italic',     'key' => 'helveticai'],
            ['name' => 'Symbol',               'key' => 'symbol'],
            ['name' => 'Times',                'key' => 'times'],
            ['name' => 'Times Bold',           'key' => 'timesb'],
            ['name' => 'Times Bold Italic',    'key' => 'timesbi'],
            ['name' => 'Times Italic',         'key' => 'timesi'],
            ['name' => 'Zapf Dingbats',        'key' => 'zapfdingbats'],

            // CID fonts
            ['name' => 'CID0 CS',  'key' => 'cid0cs'],
            ['name' => 'CID0 CT',  'key' => 'cid0ct'],
            ['name' => 'CID0 JP',  'key' => 'cid0jp'],
            ['name' => 'CID0 KR',  'key' => 'cid0kr'],

            // PDF/A fonts
            ['name' => 'PDFA Courier',              'key' => 'pdfacourier'],
            ['name' => 'PDFA Courier Bold',         'key' => 'pdfacourierb'],
            ['name' => 'PDFA Courier Bold Italic',  'key' => 'pdfacourierbi'],
            ['name' => 'PDFA Courier Italic',       'key' => 'pdfacourieri'],
            ['name' => 'PDFA Helvetica',            'key' => 'pdfahelvetica'],
            ['name' => 'PDFA Helvetica Bold',       'key' => 'pdfahelveticab'],
            ['name' => 'PDFA Helvetica Bold Italic','key' => 'pdfahelveticabi'],
            ['name' => 'PDFA Helvetica Italic',     'key' => 'pdfahelveticai'],
            ['name' => 'PDFA Symbol',               'key' => 'pdfasymbol'],
            ['name' => 'PDFA Times',                'key' => 'pdfatimes'],
            ['name' => 'PDFA Times Bold',           'key' => 'pdfatimesb'],
            ['name' => 'PDFA Times Bold Italic',    'key' => 'pdfatimesbi'],
            ['name' => 'PDFA Times Italic',         'key' => 'pdfatimesi'],
            ['name' => 'PDFA Zapf Dingbats',        'key' => 'pdfazapfdingbats'],

            // DejaVu
            ['name' => 'DejaVu Math TeX Gyre',          'key' => 'dejavumathtexgyre'],
            ['name' => 'DejaVu Sans',                    'key' => 'dejavusans'],
            ['name' => 'DejaVu Sans Bold',               'key' => 'dejavusansb'],
            ['name' => 'DejaVu Sans Bold Italic',        'key' => 'dejavusansbi'],
            ['name' => 'DejaVu Sans Condensed',          'key' => 'dejavusanscondensed'],
            ['name' => 'DejaVu Sans Condensed Bold',     'key' => 'dejavusanscondensedb'],
            ['name' => 'DejaVu Sans Condensed Bold Italic','key' => 'dejavusanscondensedbi'],
            ['name' => 'DejaVu Sans Condensed Italic',   'key' => 'dejavusanscondensedi'],
            ['name' => 'DejaVu Sans Extra Light',        'key' => 'dejavusansextralight'],
            ['name' => 'DejaVu Sans Italic',             'key' => 'dejavusansi'],
            ['name' => 'DejaVu Sans Mono',               'key' => 'dejavusansmono'],
            ['name' => 'DejaVu Sans Mono Bold',          'key' => 'dejavusansmonob'],
            ['name' => 'DejaVu Sans Mono Bold Italic',   'key' => 'dejavusansmonobi'],
            ['name' => 'DejaVu Sans Mono Italic',        'key' => 'dejavusansmonoi'],
            ['name' => 'DejaVu Serif',                   'key' => 'dejavuserif'],
            ['name' => 'DejaVu Serif Bold',              'key' => 'dejavuserifb'],
            ['name' => 'DejaVu Serif Bold Italic',       'key' => 'dejavuserifbi'],
            ['name' => 'DejaVu Serif Condensed',         'key' => 'dejavuserifcondensed'],
            ['name' => 'DejaVu Serif Condensed Bold',    'key' => 'dejavuserifcondensedb'],
            ['name' => 'DejaVu Serif Condensed Bold Italic','key' => 'dejavuserifcondensedbi'],
            ['name' => 'DejaVu Serif Condensed Italic',  'key' => 'dejavuserifcondensedi'],
            ['name' => 'DejaVu Serif Italic',            'key' => 'dejavuserifi'],

            // FreeFont
            ['name' => 'Free Mono',            'key' => 'freemono'],
            ['name' => 'Free Mono Bold',       'key' => 'freemonob'],
            ['name' => 'Free Mono Bold Italic','key' => 'freemonobi'],
            ['name' => 'Free Mono Italic',     'key' => 'freemonoi'],
            ['name' => 'Free Sans',            'key' => 'freesans'],
            ['name' => 'Free Sans Bold',       'key' => 'freesansb'],
            ['name' => 'Free Sans Bold Italic','key' => 'freesansbi'],
            ['name' => 'Free Sans Italic',     'key' => 'freesansi'],
            ['name' => 'Free Serif',           'key' => 'freeserif'],
            ['name' => 'Free Serif Bold',      'key' => 'freeserifb'],
            ['name' => 'Free Serif Bold Italic','key' => 'freeserifbi'],
            ['name' => 'Free Serif Italic',    'key' => 'freeserifi'],

            // Roboto
            ['name' => 'Roboto',            'key' => 'roboto'],
            ['name' => 'Roboto Bold',       'key' => 'robotob'],
            ['name' => 'Roboto Condensed Bold','key' => 'robotocondensedb'],

            // Ubuntu
            ['name' => 'Ubuntu',       'key' => 'ubuntu'],
            ['name' => 'Ubuntu Bold',  'key' => 'ubuntub'],

            // Unifont
            ['name' => 'Unifont',        'key' => 'unifont'],
            ['name' => 'Unifont CSUR',   'key' => 'unifont_csur'],
            ['name' => 'Unifont JP',     'key' => 'unifont_jp'],
            ['name' => 'Unifont T',      'key' => 'unifont_t'],
            ['name' => 'Unifont Upper',  'key' => 'unifont_upper'],
        ];

        $fontMap = [];
        foreach ($fonts as $font) {
            $fontMap[$font['key']] = Font::create($font)->id;
        }

        LabelPreset::create([
            'title'               => 'Лоток 1л',
            'page_width'          => 210.0,
            'page_height'         => 297.0,
            'page_margin_top'     => 10.0,
            'page_margin_right'   => 10.0,
            'page_margin_bottom'  => 10.0,
            'page_margin_left'    => 10.0,
            'cell_width'          => 78.0,
            'cell_height'         => 23.0,
            'cell_pad_top'        => 3.0,
            'cell_pad_right'      => 5.0,
            'cell_pad_bottom'     => 5.0,
            'cell_pad_left'       => 5.0,
            'barcode_position'    => 'left',
            'barcode_text_gap'    => 2.0,
            'barcode_size'        => 13.0,
            'font_id'             => $fontMap['robotocondensedb'],
            'font_size_min'       => 5.0,
            'font_size_max'       => 24.0,
            'font_size_step'      => 0.5,
            'line_height_factor'  => 1.25,
        ]);

        $labels = json_decode(file_get_contents(__DIR__ . '/../../test_100_labels.json'), true);
        $titles = array_column($labels['labels'], 'title');

        // Box distribution per shelf: [shelf1_count, shelf2_count]
        $boxDistribution = [
            [1, 2], // Cabinet 1
            [1, 2], // Cabinet 2
            [1, 2], // Cabinet 3
        ];

        $boxes = [];
        $boxCounter = 0;
        $codeSeq = 0;

        // Create stores hierarchy (cabinets → shelves → boxes)
        for ($i = 1; $i <= 3; $i++) {
            $cabinet = Store::create(['title' => "Шкаф {$i}"]);

            Code::create([
                'code'     => $this->staticUuidV7(++$codeSeq, 'cabinet'),
                'store_id' => $cabinet->id,
            ]);

            [$shelf1Count, $shelf2Count] = $boxDistribution[$i - 1];

            $shelves = [
                Store::create([
                    'title'     => "Полка {$i}-1",
                    'parent_id' => $cabinet->id,
                ]),
                Store::create([
                    'title'     => "Полка {$i}-2",
                    'parent_id' => $cabinet->id,
                ]),
            ];

            foreach ($shelves as $shelf) {
                Code::create([
                    'code'     => $this->staticUuidV7(++$codeSeq, 'shelf'),
                    'store_id' => $shelf->id,
                ]);
            }

            $shelfBoxCounts = [$shelf1Count, $shelf2Count];

            foreach ($shelfBoxCounts as $shelfIndex => $boxCount) {
                for ($b = 0; $b < $boxCount; $b++) {
                    $boxCounter++;
                    $box = Store::create([
                        'title'     => "Коробка {$boxCounter}",
                        'parent_id' => $shelves[$shelfIndex]->id,
                    ]);

                    Code::create([
                        'code'     => $this->staticUuidV7(++$codeSeq, 'box'),
                        'store_id' => $box->id,
                    ]);

                    $boxes[] = $box;
                }
            }
        }

        // Distribute 100 items across boxes
        $totalBoxes = count($boxes);
        foreach ($titles as $index => $title) {
            $box = $boxes[$index % $totalBoxes];

            $item = Item::create([
                'title'    => $title,
                'store_id' => $box->id,
            ]);

            Code::create([
                'code'    => $this->staticUuidV7(++$codeSeq, 'item'),
                'item_id' => $item->id,
            ]);
        }
    }

    /**
     * Generate a deterministic UUID v7.
     * Uses a fixed timestamp base plus a sequence number for uniqueness,
     * and SHA-256 of (prefix.seq) for the random part — so the same
     * prefix+seq always produces the same UUID.
     */
    private function staticUuidV7(int $seq, string $prefix): string
    {
        // Fixed base timestamp: 2026-01-01 00:00:00.000 UTC
        $tsMs = 1767225600000 + $seq;
        $tsHex = str_pad(dechex($tsMs), 12, '0', STR_PAD_LEFT);

        // Deterministic «random» bytes from SHA-256
        $hash = hash('sha256', "{$prefix}.{$seq}");

        $timeHigh = substr($tsHex, 0, 8);
        $timeMid  = substr($tsHex, 8, 4);

        // version 7 (4 bits) + rand_a (12 bits)
        $verRandA = '7' . substr($hash, 0, 3);

        // variant 10xx (2 bits) + first 14 bits of rand_b
        $varRandB1 = dechex(0x8000 | hexdec(substr($hash, 3, 4)));

        // remaining 48 bits of rand_b
        $randB2 = substr($hash, 7, 12);

        return sprintf('%s-%s-%s-%s-%s', $timeHigh, $timeMid, $verRandA, $varRandB1, $randB2);
    }
}
