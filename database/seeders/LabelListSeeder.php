<?php

namespace Database\Seeders;

use App\Models\LabelList;
use Illuminate\Database\Seeder;

class LabelListSeeder extends Seeder
{
    public function run(): void
    {

        // List: 111
        $list1 = LabelList::create([
            'title' => '111',
            'label_preset_id' => 1,
        ]);
        $list1->items()->sync([1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30]);

        // List: Хранилища 1
        $list2 = LabelList::create([
            'title' => 'Хранилища 1',
            'label_preset_id' => 2,
        ]);
        $list2->items()->sync([1,3,32,33,34,35,36,37,38,39,40]);
        $list2->stores()->sync([1,2,4]);

    }
}
