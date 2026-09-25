<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\UserProfile;
use App\Models\Warehouse;
use App\Support\CurrentUser;
use Illuminate\Database\Seeder;

class DemoWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = UserProfile::where('email', 'demo@demo.demo')->firstOrFail();
        CurrentUser::set($demo);

        $warehouse = Warehouse::updateOrCreate(
            ['title' => 'Домашний склад', 'user_id' => $demo->id],
            ['user_id' => $demo->id]
        );

        $workshop = Store::updateOrCreate(
            ['title' => 'Мастерская', 'user_id' => $demo->id],
            [
                'user_id'           => $demo->id,
                'warehouse_id'      => $warehouse->id,
                'warehouse_root_id' => $warehouse->id,
            ]
        );
        Store::updateOrCreate(
            ['title' => 'Верхняя полка', 'parent_id' => $workshop->id],
            [
                'title'             => 'Верхняя полка',
                'user_id'           => $demo->id,
                'warehouse_id'      => $warehouse->id,
                'warehouse_root_id' => $warehouse->id,
            ]
        );

        $desk = Store::updateOrCreate(
            ['title' => 'Рабочий стол', 'user_id' => $demo->id],
            [
                'user_id'           => $demo->id,
                'warehouse_id'      => $warehouse->id,
                'warehouse_root_id' => $warehouse->id,
            ]
        );
        Store::updateOrCreate(
            ['title' => 'Нижний ящик', 'parent_id' => $desk->id],
            [
                'title'             => 'Нижний ящик',
                'user_id'           => $demo->id,
                'warehouse_id'      => $warehouse->id,
                'warehouse_root_id' => $warehouse->id,
            ]
        );
    }
}