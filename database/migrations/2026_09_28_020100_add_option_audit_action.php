<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Событие «настройки изменены».
 *
 * Отдельное событие, а не «обновлён элемент»: у настроек нет ни создания, ни
 * удаления, и в журнале за ними стоит один-единственный факт — что человек
 * что-то у себя поменял. В payload кладём список изменённых ключей, чтобы по
 * журналу было видно, что именно, не разбирая всё состояние.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PG 12+ разрешает ADD VALUE в транзакции, если новое значение
        // в ней же не используется — здесь оно только добавляется.
        DB::statement("ALTER TYPE audit_action ADD VALUE IF NOT EXISTS 'option.updated'");
    }

    public function down(): void
    {
        // Значение перечисления из PG-типа удалить нельзя: на него уже могут
        // ссылаться записи журнала. Оно просто перестаёт встречаться.
    }
};
