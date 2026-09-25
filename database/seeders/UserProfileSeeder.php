<?php

namespace Database\Seeders;

use App\Models\UserProfile;
use App\Support\CurrentUser;
use Illuminate\Database\Seeder;

class UserProfileSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'demo',  'email' => 'demo@demo.demo',    'password' => 'demo@demo.demo'],
            ['name' => 'admin', 'email' => 'admin@admin.admin', 'password' => 'admin@admin.admin'],
            ['name' => 'user',  'email' => 'user@user.user',    'password' => 'user@user.user'],
        ];

        foreach ($users as $user) {
            UserProfile::updateOrCreate(
                ['email' => $user['email']],
                $user
            );

            // Демо-пользователь — актор всех событий демо-данных.
            if ($user['email'] === 'demo@demo.demo') {
                CurrentUser::set(UserProfile::where('email', 'demo@demo.demo')->firstOrFail());
            }
        }
    }
}