<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Поставщик попадает в журнал действий наравне с остальным каталогом:
 * появляются значения перечисления, колонка-цель и её FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PG 12+ разрешает ADD VALUE в транзакции, если новое значение
        // в ней же не используется — здесь оно только добавляется.
        foreach (['created', 'updated', 'deleted'] as $event) {
            DB::statement(sprintf(
                "ALTER TYPE audit_action ADD VALUE IF NOT EXISTS 'vendor.%s'",
                $event
            ));
        }

        Schema::table('audit_log', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable();
        });

        Schema::table('audit_log', function (Blueprint $table) {
            $table->index('vendor_id', 'audit_log_vendor_id_idx');
        });

        // nullOnDelete, как и у остальных целей: журнал переживает удаление
        // сущности, а durable-ссылка остаётся в payload.
        Schema::table('audit_log', function (Blueprint $table) {
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendor')
                ->nullOnDelete();
        });

        // CHECK перечисляет цели поимённо, поэтому на добавление колонки
        // приходится пересоздать его.
        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_single_target');
        DB::statement('ALTER TABLE audit_log ADD CONSTRAINT audit_log_single_target CHECK (' .
            'num_nonnulls(item_id, store_id, warehouse_id, label_preset_id, label_list_id, ' .
            'access_grant_id, target_user_id, category_id, property_id, property_group_id, ' .
            'dictionary_id, dictionary_value_id, unit_id, vendor_id) <= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_single_target');
        DB::statement('ALTER TABLE audit_log ADD CONSTRAINT audit_log_single_target CHECK (' .
            'num_nonnulls(item_id, store_id, warehouse_id, label_preset_id, label_list_id, ' .
            'access_grant_id, target_user_id, category_id, property_id, property_group_id, ' .
            'dictionary_id, dictionary_value_id, unit_id) <= 1)');

        Schema::table('audit_log', function (Blueprint $table) {
            // Имя ограничения создаётся с префиксом таблицы
            // (audit_log_vendor_id_foreign), а dropForeign со строкой берёт
            // её как есть, без префикса — поэтому указываем полностью.
            $table->dropForeign('audit_log_vendor_id_foreign');
            $table->dropColumn('vendor_id');
        });

        // Значения перечисления из PG-типа удалить нельзя: на него ссылается
        // заполненный журнал. Они просто перестают встречаться.
    }
};
