<?php

namespace Database\Seeders;

class DemoItemsShelfSeeder extends DemoItemsSeeder
{
    protected function storeTitle(): string
    {
        return 'Верхняя полка';
    }

    protected function items(): array
    {
        return [
            'Болт М6х10'                   => ['file' => 'image_1.jpg',  'qty' => 42],
            'Болт М8х25'                   => ['file' => 'image_2.jpg',  'qty' => 36],
            'Болт М10х30'                  => ['file' => 'image_3.jpg',  'qty' => 20],
            'Саморез 3.5х25'               => ['file' => 'image_4.jpg',  'qty' => 50],
            'Саморез 4х35'                 => ['file' => 'image_5.jpg',  'qty' => 45],
            'Саморез 4.2х16 с прессшайбой' => ['file' => 'image_6.jpg',  'qty' => 40],
            'Шуруп 5х50'                   => ['file' => 'image_7.jpg',  'qty' => 30],
            'Гайка М6'                     => ['file' => 'image_8.png',  'qty' => 48],
            'Гайка М8'                     => ['file' => 'image_9.jpg',  'qty' => 32],
            'Гайка М10'                    => ['file' => 'image_10.jpg', 'qty' => 14],
            'Гайка самоконтрящаяся'        => ['file' => 'image_11.jpg', 'qty' => 22],
            'Шайба плоская'                => ['file' => 'image_12.jpg', 'qty' => 50],
            'Шайба гровер'                 => ['file' => 'image_13.jpg', 'qty' => 38],
            'Шайба пружинная'              => ['file' => 'image_14.jpg', 'qty' => 26],
            'Хомут червячный'              => ['file' => 'image_15.jpg', 'qty' => 12],
            'Стяжка нейлоновая'            => ['file' => 'image_16.jpg', 'qty' => 36],
            'Заклёпка вытяжная'            => ['file' => 'image_17.jpg', 'qty' => 44],
            'Дюбель 6х30'                  => ['file' => 'image_18.jpg', 'qty' => 28],
            'Дюбель-гвоздь'                => ['file' => 'image_19.jpg', 'qty' => 34],
            'Анкер клиновой'               => ['file' => 'image_20.jpg', 'qty' => 8],
            'Шпилька резьбовая'            => ['file' => 'image_21.jpg', 'qty' => 6],
            'Гвоздь строительный'          => ['file' => 'image_22.jpg', 'qty' => 50],
            'Дюбель-бабочка'               => ['file' => 'image_23.jpg', 'qty' => 16],
            'Винт М3х10'                   => ['file' => 'image_24.jpg', 'qty' => 46],
            'Винт М4х20'                   => ['file' => 'image_25.jpg', 'qty' => 33],
            'Шуруп самонарезающий'         => ['file' => 'image_26.jpg', 'qty' => 24],
        ];
    }
}