<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
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
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $cabinet = Store::create([
                'title' => "Шкаф {$i}",
            ]);

            for ($j = 1; $j <= 3; $j++) {
                Store::create([
                    'title' => "Коробка {$i}-{$j}",
                    'parent_id' => $cabinet->id,
                ]);
            }
        }
    }
}
