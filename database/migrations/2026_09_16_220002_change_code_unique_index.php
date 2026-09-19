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
            // Коды могут повторяться у разных предметов (один штрихкод на
            // несколько экземпляров товара у одного и того же пользователя).
            $table->index(['code'], 'code_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('code', function (Blueprint $table) {
            $table->dropIndex('code_code_index');
            $table->unique('code', 'code_code_unique');
        });
    }
};