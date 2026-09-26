<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title_short', 16);
            $table->string('title_full', 255);
            $table->timestamps();

            $table->unique(['user_id', 'title_short'], 'unit_user_id_title_short_unique');

            // user_id cascadeOnDelete (как у warehouse): единица — часть
            // персонального справочника, и строка, осиротевшая после удаления
            // пользователя, уже никому не нужна. Наследует ещё и nullOnDelete
            // в label_preset/item/store, но там NULL у пользователя означает
            // «системный шаблон» или «предмет чужого склада», и те таблицы
            // переживают удаление владельца.
            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit');
    }
};
