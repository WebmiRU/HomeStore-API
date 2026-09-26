<?php

namespace Database\Seeders;

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
        $this->call([
            UserProfileSeeder::class,
            FontSeeder::class,
            LabelPresetSeeder::class,
            ThumbnailSeeder::class,
            DemoWarehouseSeeder::class,
            DemoItemsShelfSeeder::class,
            DemoItemsDrawerSeeder::class,
            // Каталог и свойства идут после предметов: раскладывать их по
            // категориям и заполнять значениями можно только когда предметы
            // уже заведены.
            DemoCatalogSeeder::class,
            DemoLabelListSeeder::class,
        ]);
    }
}