<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Свойство не привязано к категории ни одной колонкой: набор
        // свойств категории вычисляется по уже заполненным значениям
        // (см. ItemPropertyService::forCategory). Здесь лежит только
        // описание свойства — чем оно заполняется.
        Schema::create('property', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('title');
            $table->string('type', 16)->default('string');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('dictionary_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'title'], 'property_user_id_title_unique');

            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // nullOnDelete: свойство не должно пропасть из-под уже
            // заполненных значений при удалении группы/единицы/справочника —
            // оно просто останется без них, а значения сохранятся.
            $table->foreign('group_id')
                ->references('id')
                ->on('property_group')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('unit_id')
                ->references('id')
                ->on('unit')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('dictionary_id')
                ->references('id')
                ->on('dictionary')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property');
    }
};
