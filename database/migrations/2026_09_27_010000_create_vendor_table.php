<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Поставщик (производитель), у которого берут предметы. Отдельный справочник,
 * а не словарное значение: у поставщика есть описание и логотип, которых
 * у значения справочника нет и которые не имеет смысла заводить на каждое.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('title');
            // Описание свободное и необязательное: у мелких поставщиков его
            // просто нет, а пустую строку хранить незачем — NULL честнее.
            $table->text('description')->nullable();
            // Логотип — одна картинка на поставщика, поэтому не связь
            // many-to-many, как фото у предмета, а ссылка на image.
            // nullOnDelete: картинка может быть удалена как неиспользуемая,
            // и поставщик должен остаться — без логотипа.
            $table->unsignedBigInteger('logo_id')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'title'], 'vendor_user_id_title_unique');

            // user_id cascadeOnDelete, как у unit и category: справочник
            // принадлежит пользователю целиком.
            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('logo_id')
                ->references('id')
                ->on('image')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor');
    }
};
