<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Списания и пополнения — не «лог», а транзакции с возможностью отката,
        // поэтому таблиц две: операция (комментарий, автор, направление) и её
        // строки (что именно и сколько). Направление хранится флагом в одной
        // таблице, а не двумя таблицами: списание и пополнение структурно
        // идентичны, а историю предмета («что с ним случалось») нужно собирать
        // одним запросом по одному индексу, а не UNION двух таблиц.
        Schema::create('stock_operation', function (Blueprint $table) {
            $table->id();

            // Кто провёл операцию. nullOnDelete: удаление пользователя не должно
            // уносить его транзакции — они остаются в логе, просто без автора.
            $table->unsignedBigInteger('user_id')->nullable();

            // writeoff | replenish. Откат списания — это replenish, а откат
            // пополнения — writeoff, поэтому у направления ровно два значения,
            // а «это был возврат» выражается ссылкой reversed_operation_id.
            $table->string('direction', 16);

            // «Куда списали / откуда пополнили». Необязателен: заполнять его
            // должен быть возможностью, а не условием.
            $table->text('comment')->nullable();

            // Указатель на откатываемую операцию. Несколько частичных откатов
            // одной операции — норма, поэтому уникачности здесь нет.
            $table->unsignedBigInteger('reversed_operation_id')->nullable();

            // Дублирует «откачена ли операция» ради списка и фильтров.
            // Денормализация сознательная: считать признак агрегатом по строкам
            // в каждом запросе журнала дороже, а расхождение исключено — все
            // записи идут через StockOperationService в одной транзакции.
            $table->timestamp('reversed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'stock_operation_user_created_index');
            $table->index('reversed_operation_id', 'stock_operation_reversed_index');
            $table->index('reversed_at', 'stock_operation_reversed_at_index');

            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('reversed_operation_id')
                ->references('id')
                ->on('stock_operation')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('stock_operation_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');

            // Предмет мог быть удалён — движение в логе остаётся, поэтому
            // ссылка nullable. Название хранится снимком: переименование
            // предмета не должно переписывать историю.
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->text('item_title');

            // Для строк отката — строка исходной операции, которую возвращают.
            // Связать их по item_id нельзя: один и тот же предмет может
            // встретиться в операции дважды (код отсканировали повторно).
            $table->unsignedBigInteger('source_row_id')->nullable();

            // Всегда положительное: знак задаёт direction операции.
            $table->bigInteger('quantity');

            // Сколько из quantity уже вернули. Держится в колонке, а не
            // считается суммой строк-откатов: иначе каждый показ журнала и
            // каждая проверка «можно ли ещё откатить» требовали бы агрегата.
            $table->bigInteger('reversed_quantity')->default(0);

            // Снимок остатка на момент операции — для сверки и разбора расхождений.
            $table->bigInteger('before')->nullable();
            $table->bigInteger('after')->nullable();

            $table->timestamps();

            $table->index('operation_id', 'stock_operation_item_operation_index');
            $table->index('item_id', 'stock_operation_item_item_index');
            $table->index('source_row_id', 'stock_operation_item_source_index');

            // Журнал фильтруется по владельцу предмета: операция видна тому,
            // кому принадлежит товар, а не только тому, кто её провёл.
            $table->index('owner_id', 'stock_operation_item_owner_index');

            $table->foreign('operation_id')
                ->references('id')
                ->on('stock_operation')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('item_id')
                ->references('id')
                ->on('item')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('owner_id')
                ->references('id')
                ->on('user')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('source_row_id')
                ->references('id')
                ->on('stock_operation_item')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        // Ограничения на уровне БД, а не только в валидации: единственный способ
        // гарантировать, что вернуть больше, чем списали, нельзя даже при
        // гонке двух параллельных откатов.
        DB::statement('ALTER TABLE stock_operation ADD CONSTRAINT stock_operation_direction_check CHECK (direction IN (\'writeoff\', \'replenish\'))');
        DB::statement('ALTER TABLE stock_operation_item ADD CONSTRAINT stock_operation_item_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE stock_operation_item ADD CONSTRAINT stock_operation_item_reversed_check CHECK (reversed_quantity >= 0 AND reversed_quantity <= quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_operation_item');
        Schema::dropIfExists('stock_operation');
    }
};
