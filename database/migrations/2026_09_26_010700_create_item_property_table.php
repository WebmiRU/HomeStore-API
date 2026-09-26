<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Значения свойств — EAV, ровно как в base220: у одного свойства на
        // предмете может быть несколько значений («Цвет: белый, серый»),
        // поэтому (item_id, property_id) не уникально, а порядок задаёт sort.
        Schema::create('item_property', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('property_id');
            $table->text('value')->nullable();
            $table->unsignedBigInteger('dictionary_value_id')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index('item_id', 'item_property_item_id_index');

            // Поиск набора свойств категории идёт именно по property_id:
            // без индекса он сканировал бы всю таблицу значений.
            $table->index('property_id', 'item_property_property_id_index');

            $table->foreign('item_id')
                ->references('id')
                ->on('item')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // cascadeOnDelete: удалённое свойство уносит свои значения,
            // иначе они остались бы нечитаемыми — описания у них уже нет.
            $table->foreign('property_id')
                ->references('id')
                ->on('property')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('dictionary_value_id')
                ->references('id')
                ->on('dictionary_value')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_property');
    }
};
