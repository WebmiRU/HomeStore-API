<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
        });

        $hash = Hash::make('admin');

        DB::table('user')->where('email', 'e.wolf@webmi.ru')->update(['password' => $hash]);

        DB::table('user')->updateOrInsert(
            ['email' => 'admin@admin.admin'],
            ['name' => 'Admin', 'password' => $hash, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('user')->where('email', 'admin@admin.admin')->delete();

        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};