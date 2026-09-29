<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Строки частичного списания в журнале движений.
 *
 * Обычная строка операции — это целые штуки: списали 3, значит и в журнале 3.
 * При частичном списании расход дробный и штуки могут не измениться вовсе:
 * списали 300 мл из бутылки — количество осталось тем же, а израсходовано
 * 300 мл. Такая строка описывается не целым числом, а расходом по свойству:
 *
 *   amount / property_before / property_after — сколько было, сколько списали
 *   и сколько осталось по этому свойству внутри текущей штуки.
 *
 * before/after остаются числами штук предмета: по ним видно, не опустела ли
 * штука целиком, и по ним же видно, что предмет убыл хотя бы на штуку.
 *
 * Поле quantity у таких строк равно нулю: списать можно, не изменив количество.
 * Поэтому проверка «quantity > 0» заменяется на «либо целые штуки, либо расход
 * по свойству» — иначе строка частичного списания просто не сохранилась бы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_operation_item', function (Blueprint $table): void {
            $table->unsignedBigInteger('property_id')->nullable();

            // Название свойства снимком: свойство потом переименуют или
            // удалят, а журнал движений читают спустя месяцы, и строка без
            // названия превращается в «списано 300 по id 7».
            $table->string('property_title')->nullable();

            $table->double('amount')->nullable();
            $table->double('property_before')->nullable();
            $table->double('property_after')->nullable();

            // Возвращено по строке: у обычной строки это целые штуки в
            // quantity, у частичной — дробный расход в amount. Отдельная
            // колонка нужна, потому что вернуть можно не всё, а часть.
            $table->double('reversed_amount')->default(0);
        });

        // Обе проверки переписываются целиком: вторая ссылается на первую
        // и на новую колонку, поэтому старую тоже нужно убрать — иначе
        // Postgres откажется добавлять одноимённую.
        DB::statement('ALTER TABLE stock_operation_item DROP CONSTRAINT stock_operation_item_quantity_check');
        DB::statement('ALTER TABLE stock_operation_item DROP CONSTRAINT stock_operation_item_reversed_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_operation_item
            ADD CONSTRAINT stock_operation_item_quantity_check
            CHECK (
                (quantity > 0 AND amount IS NULL)
                OR (quantity >= 0 AND amount > 0)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE stock_operation_item
            ADD CONSTRAINT stock_operation_item_reversed_check
            CHECK (
                reversed_quantity >= 0 AND reversed_quantity <= quantity
                AND reversed_amount >= 0
                AND (amount IS NULL OR reversed_amount <= amount)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE stock_operation_item
            ADD CONSTRAINT stock_operation_item_property_id_foreign
            FOREIGN KEY (property_id) REFERENCES property(id)
            ON UPDATE CASCADE ON DELETE SET NULL
        SQL);

        Schema::table('stock_operation_item', function (Blueprint $table): void {
            $table->index('property_id', 'stock_operation_item_property_id_index');
        });
    }

    public function down(): void
    {
        // Порядок отката обратный порядку применения: сначала ограничения,
        // потом индекс, потом колонки — колонка property_id уносит с собой и
        // внешний ключ, поэтому ключ снимаем явно и до неё.
        DB::statement('ALTER TABLE stock_operation_item DROP CONSTRAINT IF EXISTS stock_operation_item_property_id_foreign');
        DB::statement('ALTER TABLE stock_operation_item DROP CONSTRAINT IF EXISTS stock_operation_item_reversed_check');
        DB::statement('ALTER TABLE stock_operation_item DROP CONSTRAINT IF EXISTS stock_operation_item_quantity_check');

        Schema::table('stock_operation_item', function (Blueprint $table): void {
            $table->dropIndex('stock_operation_item_property_id_index');
            $table->dropColumn(['property_id', 'property_title', 'amount', 'property_before', 'property_after', 'reversed_amount']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE stock_operation_item
            ADD CONSTRAINT stock_operation_item_quantity_check CHECK (quantity > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE stock_operation_item
            ADD CONSTRAINT stock_operation_item_reversed_check
            CHECK (reversed_quantity >= 0 AND reversed_quantity <= quantity)
        SQL);
    }
};
