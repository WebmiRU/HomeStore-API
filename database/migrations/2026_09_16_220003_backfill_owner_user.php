<?php

use App\Models\UserProfile;
use App\Services\UserRegistrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $email = 'e.wolf@webmi.ru';

        $user = UserProfile::where('email', $email)->first();

        if (! $user) {
            $user = app(UserRegistrationService::class)->register([
                'email' => $email,
                'name'  => 'Евгений Вольф',
            ]);
        }

        foreach (['code', 'item', 'label_list', 'label_preset', 'store', 'warehouse'] as $table) {
            DB::table($table)->whereNull('user_id')->update(['user_id' => $user->id]);
        }
    }

    public function down(): void
    {
        $user = UserProfile::where('email', 'e.wolf@webmi.ru')->first();

        if ($user) {
            foreach (['code', 'item', 'label_list', 'label_preset', 'store', 'warehouse'] as $table) {
                DB::table($table)->where('user_id', $user->id)->update(['user_id' => null]);
            }
        }
    }
};