<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Язык интерфейса и сообщений.
 *
 * Русский и английский, русский по умолчанию. Хранится у пользователя, а не
 * в браузере: язык должен переехать на другое устройство вместе с человеком.
 *
 * CHECK со списком языков — тот же приём, что у operation_mode: правила
 * запроса видят присланное значение, а не то, что уже лежит в базе.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option', function (Blueprint $table) {
            $table->string('locale', 5)->default('ru')->after('operation_mode');
        });

        // CHECK — через ALTER TABLE: Blueprint::check() в этой версии нет.
        DB::statement("ALTER TABLE option ADD CONSTRAINT option_locale_check CHECK (locale in ('ru', 'en'))");
    }

    public function down(): void
    {
        Schema::table('option', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
