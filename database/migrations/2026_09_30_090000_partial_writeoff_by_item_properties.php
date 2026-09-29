<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Частичное списание предмета по его числовым свойствам.
 *
 * Обычное свойство предмета — это описание: «диаметр 8 мм», «объём 1000 мл».
 * Частично списываемое свойство — это запас, который расходуется: у бутылки
 * сиропа «Объём» означает, сколько миллилитров в одной бутылке, и списать
 * можно двести, не выпивая бутылку.
 *
 * Значение такого свойства остаётся в item_property и означает норму на одну
 * штуку: у двух бутылок по 1000 мл в карточке написано «1000 мл», а всего на
 * складе 2000.
 *
 * Сколько из них осталось — целые штуки плюс остаток внутри текущей штуки.
 * Штуки хранятся в item.quantity, а остаток текущей штуки — здесь:
 *
 *   item_partial_remaining.remaining
 *
 * Суммарный остаток свойства, который видит человек:
 *
 *   (quantity - 1) × норма + remaining
 *
 * Проверка на примере двух бутылок по 1000 мл: quantity 2, remaining 1000,
 * всего 2000. Списали 1100 — quantity 1, remaining 900, всего 900: одна
 * бутылка ушла целиком, вторая опустела на 100 мл. Списали ещё 900 —
 * quantity 0, remaining 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item', function (Blueprint $table): void {
            $table->boolean('partial_writeoff')->default(false);
        });

        // Настройки расхода: какие свойства предмета расходуются частями,
        // с каким шагом по умолчанию и является ли обнуление этого свойства
        // поводом списать предмет целиком.
        Schema::create('item_partial_property', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('property_id');

            // Шаг списания по умолчанию: значение по умолчанию, а не
            // ограничение — перед отправкой операции его можно поменять.
            $table->double('step')->default(1);

            // Повод для полного списания. Списание всего расходуемого — нет,
            // когда обнулились все отмеченные свойства.
            $table->boolean('is_full_reason')->default(true);

            // Порядок предложения в строке списания.
            $table->integer('sort')->default(0);

            $table->timestamps();

            $table->unique(['item_id', 'property_id'], 'item_partial_property_item_property_unique');
            $table->index('property_id', 'item_partial_property_property_id_index');

            // Настройка без предмета не имеет смысла, и удалённого предмета
            // настройки хранить незачем.
            $table->foreign('item_id')
                ->references('id')
                ->on('item')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Свойство удалили — настройка расхода тоже исчезает.
            $table->foreign('property_id')
                ->references('id')
                ->on('property')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        // Остаток внутри текущей штуки, по одному на расходуемое свойство.
        Schema::create('item_partial_remaining', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('property_id');
            $table->double('remaining')->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'property_id'], 'item_partial_remaining_item_property_unique');
            $table->index('property_id', 'item_partial_remaining_property_id_index');

            $table->foreign('item_id')
                ->references('id')
                ->on('item')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('property_id')
                ->references('id')
                ->on('property')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_partial_remaining');
        Schema::dropIfExists('item_partial_property');

        Schema::table('item', function (Blueprint $table): void {
            $table->dropColumn('partial_writeoff');
        });
    }
};
