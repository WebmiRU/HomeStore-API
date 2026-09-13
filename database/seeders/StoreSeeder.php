<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            ['id' => 1,  'title' => 'Шкаф 1',           'title_print' => null, 'parent_id' => null],
            ['id' => 2,  'title' => 'Полка 1-1',        'title_print' => null, 'parent_id' => 1],
            ['id' => 3,  'title' => 'Полка 1-2',        'title_print' => null, 'parent_id' => 1],
            ['id' => 4,  'title' => 'Коробка 1',        'title_print' => null, 'parent_id' => 2],
            ['id' => 5,  'title' => 'Коробка 2',        'title_print' => null, 'parent_id' => 3],
            ['id' => 6,  'title' => 'Коробка 3',        'title_print' => null, 'parent_id' => 3],
            ['id' => 7,  'title' => 'Шкаф 2',           'title_print' => null, 'parent_id' => null],
            ['id' => 8,  'title' => 'Полка 2-1',        'title_print' => null, 'parent_id' => 7],
            ['id' => 9,  'title' => 'Полка 2-2',        'title_print' => null, 'parent_id' => 7],
            ['id' => 10, 'title' => 'Коробка 4',        'title_print' => null, 'parent_id' => 8],
            ['id' => 11, 'title' => 'Коробка 5',        'title_print' => null, 'parent_id' => 9],
            ['id' => 12, 'title' => 'Коробка 6',        'title_print' => null, 'parent_id' => 9],
            ['id' => 13, 'title' => 'Шкаф 3',           'title_print' => null, 'parent_id' => null],
            ['id' => 14, 'title' => 'Полка 3-1',        'title_print' => null, 'parent_id' => 13],
            ['id' => 15, 'title' => 'Полка 3-2',        'title_print' => null, 'parent_id' => 13],
            ['id' => 16, 'title' => 'Коробка 7',        'title_print' => null, 'parent_id' => 14],
            ['id' => 17, 'title' => 'Коробка 8',        'title_print' => null, 'parent_id' => 15],
            ['id' => 18, 'title' => 'Коробка 9',        'title_print' => null, 'parent_id' => 15],
            ['id' => 19, 'title' => 'Шкаф в котельной', 'title_print' => null, 'parent_id' => null],
            ['id' => 20, 'title' => 'Полка А',          'title_print' => null, 'parent_id' => 19],
            ['id' => 21, 'title' => 'Полка Б',          'title_print' => null, 'parent_id' => 19],
            ['id' => 23, 'title' => 'Полка В',          'title_print' => null, 'parent_id' => 19],
            ['id' => 24, 'title' => 'Полка Г',          'title_print' => null, 'parent_id' => 19],
            ['id' => 25, 'title' => 'Полка Д',          'title_print' => null, 'parent_id' => 19],
            ['id' => 26, 'title' => 'Коробка 1',        'title_print' => null, 'parent_id' => 21],
        ];

        foreach ($stores as $store) {
            DB::table('store')->updateOrInsert(
                ['id' => $store['id']],
                [
                    'title' => $store['title'],
                    'title_print' => $store['title_print'],
                    'parent_id' => $store['parent_id'],
                    'updated_at' => now(),
                ]
            );
        }
    }
}
