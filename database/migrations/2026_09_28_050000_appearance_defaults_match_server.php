<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Умолчания оформления приведены к тем, что отдаёт сервер.
 *
 * Миграция 040000 создала колонки со значениями по умолчанию от первого
 * варианта интерфейса: тёмная тема и зелёный акцент. Теперь умолчание — «как
 * в системе» и синий, и база обязана знать то же самое, что и код: иначе
 * строка настроек, созданная в обход сервиса, оказалась бы с другим
 * оформлением, чем у человека, который ничего не выбирал.
 *
 * Существующие строки не трогаются: выбор пользователя священнее умолчания.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option', function (Blueprint $table): void {
            $table->string('theme', 16)->default('system')->change();
            $table->string('accent', 16)->default('blue')->change();
        });
    }

    public function down(): void
    {
        Schema::table('option', function (Blueprint $table): void {
            $table->string('theme', 16)->default('dark')->change();
            $table->string('accent', 16)->default('green')->change();
        });
    }
};
