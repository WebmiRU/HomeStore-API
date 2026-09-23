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
            StoreSeeder::class,
            ItemSeeder::class,
            LabelPresetSeeder::class,
            CodeSeeder::class,
            LabelListSeeder::class,
            ThumbnailSeeder::class,
            DemoWarehouseSeeder::class,
            DemoItemsShelfSeeder::class,
            DemoItemsDrawerSeeder::class,
        ]);
    }
}