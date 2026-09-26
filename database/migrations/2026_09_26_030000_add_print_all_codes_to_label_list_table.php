<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_list', function (Blueprint $table) {
            // По умолчанию печатается главный код предмета — тот же, что
            // показывается в карточке. Крыжик нужен для случая, когда на
            // вещь наклеено несколько этикеток и на руки нужны все.
            $table->boolean('print_all_codes')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('label_list', function (Blueprint $table) {
            $table->dropColumn('print_all_codes');
        });
    }
};
