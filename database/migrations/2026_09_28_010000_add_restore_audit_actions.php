<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * События «восстановлено» для мягко удаляемых сущностей.
 *
 * Без них журнал показывал только удаление, и по истории нельзя было
 * понять, вернули запись или завели новую с тем же именем: обе выглядели бы
 * одинаково — «удалено, потом создано». Отдельное событие и отдельная запись
 * в журнале дают честную картину.
 *
 * Значения добавляются по одному на каждую мягко удаляемую сущность. У
 * выдач прав такого события нет: они удаляются физически, восстанавливать
 * их нечем.
 */
return new class extends Migration
{
    /** Таблица => префикс события (совпадает с именем в AuditAction). */
    private const PREFIXES = [
        'item' => 'item',
        'store' => 'store',
        'warehouse' => 'warehouse',
        'label_preset' => 'label_preset',
        'label_list' => 'label_list',
        'user' => 'user',
        'category' => 'category',
        'property' => 'property',
        'property_group' => 'property_group',
        'dictionary' => 'dictionary',
        'dictionary_value' => 'dictionary_value',
        'unit' => 'unit',
        'vendor' => 'vendor',
    ];

    public function up(): void
    {
        foreach (array_keys(self::PREFIXES) as $prefix) {
            // PG 12+ разрешает ADD VALUE в транзакции, если новое значение
            // в ней же не используется — здесь оно только добавляется.
            DB::statement(sprintf(
                "ALTER TYPE audit_action ADD VALUE IF NOT EXISTS '%s.restored'",
                $prefix
            ));
        }
    }

    public function down(): void
    {
        // Значения перечисления из PG-типа удалить нельзя: на них ссылается
        // заполненный журнал. Они просто перестают встречаться.
    }
};
