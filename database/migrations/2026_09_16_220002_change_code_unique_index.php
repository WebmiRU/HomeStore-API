<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('code', function (Blueprint $table) {
            $table->dropUnique('code_code_unique');
            $table->unique(['code', 'user_id'], 'code_code_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('code', function (Blueprint $table) {
            $table->dropUnique('code_code_user_id_unique');
            $table->unique('code', 'code_code_unique');
        });
    }
};