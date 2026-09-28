<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тема оформления и акцентный цвет в настройках пользователя.
 *
 * Это две разные вещи, и в таблице они разные колонки: тема — это про
 * комфорт (светло или темно), акцент — про внешний вид (какого цвета кнопки).
 * Смешивать их в одно поле нельзя: «светлая пурпурная» — это пересечение двух
 * настроек, и хранить его одним значением значит плодить комбинации вместо
 * двух независимых списков.
 *
 * Строка настроек заводится лениво, при первом сохранении, поэтому значения
 * по умолчанию живут в коде (Option::THEME_DARK, Option::ACCENT_GREEN), а не
 * здесь: иначе умолчание было бы в двух местах. В базе значение нужно только
 * для тех строк, что уже созданы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option', function (Blueprint $table): void {
            $table->string('theme', 16)->default('dark');
            $table->string('accent', 16)->default('green');
        });
    }

    public function down(): void
    {
        Schema::table('option', function (Blueprint $table): void {
            $table->dropColumn(['theme', 'accent']);
        });
    }
};
