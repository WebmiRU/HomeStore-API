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

        for ($i = 1; $i <= 3; $i++) {
            $cabinet = Store::create([
                'title' => "Шкаф {$i}",
            ]);

            Code::create([
                'code' => Str::orderedUuid(),
                'store_id' => $cabinet->id,
            ]);

            for ($j = 1; $j <= 3; $j++) {
                $box = Store::create([
                    'title' => "Коробка {$i}-{$j}",
                    'parent_id' => $cabinet->id,
                ]);

                Code::create([
                    'code' => Str::orderedUuid(),
                    'store_id' => $box->id,
                ]);

                $itemCount = rand(3, 4);

                for ($k = 0; $k < $itemCount; $k++) {
                    $item = Item::create([
                        'title' => $tools[$toolIndex++],
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
