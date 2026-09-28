<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Настройка «запоминать режим работы».
 *
 * Пока она есть, режим работы главной (поиск, пополнение, списание) живёт
 * всегда: выбрал «Пополнить» — и после перезагрузки страницы он тот же.
 * Выключенная настройка означает «не помнить»: страница начинает с режима по
 * умолчанию, а переключение работает только до перезагрузки.
 *
 * По умолчанию включено, потому что до появления настройки режим и так
 * сохранялся — в sessionStorage. Выключать его — осознанный выбор человека, а
 * не то, что он получит из коробки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option', function (Blueprint $table) {
            $table->boolean('remember_operation_mode')->default(true)->after('operation_mode');
        });
    }

    public function down(): void
    {
        Schema::table('option', function (Blueprint $table) {
            $table->dropColumn('remember_operation_mode');
        });
    }
};
