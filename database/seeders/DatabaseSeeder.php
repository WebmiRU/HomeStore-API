<?php

namespace Database\Seeders;

use App\Models\Code;
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
            }
        }
    }
}
