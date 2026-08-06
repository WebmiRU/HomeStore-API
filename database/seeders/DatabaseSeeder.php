<?php

namespace Database\Seeders;

use App\Models\Code;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        $tools = [
            'Пассатижи', 'Отвёртка', 'Бокорезы', 'Дрель',
            'Молоток', 'Ножовка', 'Рубанок', 'Стамеска',
            'Шуруповёрт', 'Гаечный ключ', 'Уровень', 'Рулетка',
            'Напильник', 'Зубило', 'Лобзик', 'Струбцина',
            'Шлифмашинка', 'Клещи', 'Тиски', 'Болгарка',
            'Перфоратор', 'Электролобзик', 'Фрезер', 'Штангенциркуль',
            'Микрометр', 'Набор свёрл', 'Набор бит', 'Плоскогубцы',
            'Круглогубцы', 'Торцевой ключ', 'Разводной ключ',
            'Ножницы по металлу', 'Кусачки', 'Сварочный аппарат',
            'Циркулярная пила',
        ];
        shuffle($tools);
        $toolIndex = 0;

        // Box distribution per shelf: [shelf1_count, shelf2_count]
        $boxDistribution = [
            [0, 3], // Cabinet 1: shelf 1 empty, shelf 2 has 3 boxes
            [1, 2], // Cabinet 2: shelf 1 has 1 box, shelf 2 has 2 boxes
            [2, 1], // Cabinet 3: shelf 1 has 2 boxes, shelf 2 has 1 box
        ];

        $boxCounter = 0;

        for ($i = 1; $i <= 3; $i++) {
            $cabinet = Store::create([
                'title' => "Шкаф {$i}",
            ]);

            Code::create([
                'code' => Str::orderedUuid(),
                'store_id' => $cabinet->id,
            ]);

            [$shelf1Count, $shelf2Count] = $boxDistribution[$i - 1];

            $shelves = [
                Store::create([
                    'title' => "Полка {$i}-1",
                    'parent_id' => $cabinet->id,
                ]),
                Store::create([
                    'title' => "Полка {$i}-2",
                    'parent_id' => $cabinet->id,
                ]),
            ];

            foreach ($shelves as $shelf) {
                Code::create([
                    'code' => Str::orderedUuid(),
                    'store_id' => $shelf->id,
                ]);
            }

            $shelfBoxCounts = [$shelf1Count, $shelf2Count];

            foreach ($shelfBoxCounts as $shelfIndex => $boxCount) {
                for ($b = 0; $b < $boxCount; $b++) {
                    $boxCounter++;
                    $box = Store::create([
                        'title' => "Коробка {$boxCounter}",
                        'parent_id' => $shelves[$shelfIndex]->id,
                    ]);

                    Code::create([
                        'code' => Str::orderedUuid(),
                        'store_id' => $box->id,
                    ]);

                    $itemCount = rand(3, 4);

                    for ($k = 0; $k < $itemCount; $k++) {
                        $item = Item::create([
                            'title' => $tools[$toolIndex++],
                            'store_id' => $box->id,
                        ]);

                        Code::create([
                            'code' => Str::orderedUuid(),
                            'item_id' => $item->id,
                        ]);
                    }
                }
            }
        }
    }
}
