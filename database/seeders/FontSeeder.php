<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FontSeeder extends Seeder
{
    public function run(): void
    {
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

        foreach ($fonts as $font) {
            DB::table('font')->updateOrInsert(
                ['key' => $font['key']],
                ['name' => $font['name'], 'updated_at' => now()]
            );
        }
    }
}
