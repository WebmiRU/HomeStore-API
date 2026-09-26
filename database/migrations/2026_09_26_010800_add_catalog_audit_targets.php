<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Каталог/свойства/единицы/справочники — до сих пор не были видны в журнале
 * действий. Здесь появляются их колонки-цели и значения перечисления.
 */
return new class extends Migration
{
    /** Таблица-источник => колонка-цель в audit_log. */
    private const TARGETS = [
        'category'        => 'category_id',
        'property'        => 'property_id',
        'property_group'  => 'property_group_id',
        'dictionary'      => 'dictionary_id',
        'dictionary_value' => 'dictionary_value_id',
        'unit'            => 'unit_id',
    ];

    public function up(): void
    {
        foreach (array_keys(self::TARGETS) as $table) {
            foreach (['created', 'updated', 'deleted'] as $event) {
                // PG 12+ разрешает ADD VALUE в транзакции, если новое значение
                // не используется в ней же — здесь оно только добавляется.
                DB::statement(sprintf(
                    "ALTER TYPE audit_action ADD VALUE IF NOT EXISTS '%s.%s'",
                    $table,
                    $event
                ));
            }
        }

        foreach (self::TARGETS as $table => $column) {
            Schema::table('audit_log', function (Blueprint $blueprint) use ($column) {
                $blueprint->unsignedBigInteger($column)->nullable();
            });
        }

        foreach (self::TARGETS as $table => $column) {
            Schema::table('audit_log', function (Blueprint $blueprint) use ($column) {
                $blueprint->index($column, "audit_log_{$column}_idx");
            });

            // nullOnDelete, как и у остальных целей: журнал переживает удаление
            // сущности, а durable-ссылка остаётся в payload.
            Schema::table('audit_log', function (Blueprint $blueprint) use ($column, $table) {
                $blueprint->foreign($column)->references('id')->on($table)->nullOnDelete();
            });
        }

        // CHECK перечисляет цели поимённо, поэтому на добавление колонок
        // приходится пересоздать его.
        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_single_target');
        DB::statement('ALTER TABLE audit_log ADD CONSTRAINT audit_log_single_target CHECK (' .
            'num_nonnulls(item_id, store_id, warehouse_id, label_preset_id, label_list_id, ' .
            'access_grant_id, target_user_id, category_id, property_id, property_group_id, ' .
            'dictionary_id, dictionary_value_id, unit_id) <= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_single_target');
        DB::statement('ALTER TABLE audit_log ADD CONSTRAINT audit_log_single_target CHECK (' .
            'num_nonnulls(item_id, store_id, warehouse_id, label_preset_id, label_list_id, ' .
            'access_grant_id, target_user_id) <= 1)');

        foreach (array_reverse(self::TARGETS) as $column) {
            Schema::table('audit_log', function (Blueprint $blueprint) use ($column) {
                // Имя ограничения создаётся с префиксом таблицы
                // (audit_log_unit_id_foreign), а dropForeign со строкой берёт
                // её как есть, без префикса — поэтому указываем полностью.
                $blueprint->dropForeign("audit_log_{$column}_foreign");
                $blueprint->dropColumn($column);
            });
        }

        // Значения перечисления из PG-типа удалить нельзя, и пересоздать тип
        // здесь нельзя: на него ссылается заполненный журнал. Поэтому down()
        // оставляет значения в audit_action — они просто перестают встречаться.
    }
};
