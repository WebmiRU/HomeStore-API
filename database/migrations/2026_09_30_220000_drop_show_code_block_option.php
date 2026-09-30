<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Убрана настройка «показывать блок «Код» внизу страниц».
 *
 * Блок удалён из интерфейса вместе с поиском кода в строке поиска: там код
 * теперь ищется сам, если введён буквально. Настройка осталась бы навсегда
 * ключом, который нигде не читается, и лишней миграцией при обновлении
 * базы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option', function (Blueprint $table): void {
            $table->dropColumn('show_code_block');
        });
    }

    public function down(): void
    {
        Schema::table('option', function (Blueprint $table): void {
            $table->boolean('show_code_block')->default(true);
        });
    }
};
