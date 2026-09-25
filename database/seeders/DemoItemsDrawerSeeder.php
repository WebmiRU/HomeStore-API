<?php

namespace Database\Seeders;

class DemoItemsDrawerSeeder extends DemoItemsSeeder
{
    protected function storeTitle(): string
    {
        return 'Нижний ящик';
    }

    protected function items(): array
    {
        return [
            'Кабель USB'               => ['file' => 'image_27.png', 'qty' => 3],
            'Кабель HDMI'              => ['file' => 'image_28.jpg', 'qty' => 2],
            'Зарядное устройство'      => ['file' => 'image_29.jpg', 'qty' => 1],
            'Скрепки канцелярские'     => ['file' => 'image_30.jpg', 'qty' => 30],
            'Карандаш простой'         => ['file' => 'image_31.jpg', 'qty' => 8],
            'Ручка шариковая'          => ['file' => 'image_32.jpg', 'qty' => 12],
            'Маркер перманентный'      => ['file' => 'image_33.jpg', 'qty' => 5],
            'Ластик'                   => ['file' => 'image_34.jpg', 'qty' => 4],
            'Стикеры'                  => ['file' => 'image_35.png', 'qty' => 18],
            'Ножницы'                  => ['file' => 'image_36.jpg', 'qty' => 2],
            'Степлер'                  => ['file' => 'image_37.jpg', 'qty' => 1],
            'Скобы для степлера'       => ['file' => 'image_38.jpg', 'qty' => 25],
            'Изолента'                 => ['file' => 'image_39.jpg', 'qty' => 6],
            'Скотч'                    => ['file' => 'image_40.jpg', 'qty' => 4],
            'Линейка'                  => ['file' => 'image_41.jpg', 'qty' => 3],
            'Батарейки AA'             => ['file' => 'image_42.jpg', 'qty' => 10],
            'Батарейки AAA'            => ['file' => 'image_43.jpg', 'qty' => 8],
            'Флешка USB'               => ['file' => 'image_44.jpg', 'qty' => 2],
            'Клей-карандаш'            => ['file' => 'image_45.jpg', 'qty' => 5],
            'Канцелярский нож'         => ['file' => 'image_46.jpg', 'qty' => 2],
            'Кнопки канцелярские'      => ['file' => 'image_47.jpg', 'qty' => 20],
            'Булавки'                  => ['file' => 'image_48.jpg', 'qty' => 15],
            'Отвёртка'                 => ['file' => 'image_49.jpg', 'qty' => 1],
            'Пинцет'                   => ['file' => 'image_50.jpg', 'qty' => 1],
        ];
    }
}