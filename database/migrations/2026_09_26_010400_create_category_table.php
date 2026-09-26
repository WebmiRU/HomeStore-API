<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('title');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->index('parent_id', 'category_parent_id_index');

            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('parent_id')
                ->references('id')
                ->on('category')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        // Названия уникальны среди соседей, а не во всём каталоге: «Зимнее»
        // вполне может встретиться и в «Одежде», и в «Шинном деле».
        // Обычный unique(user_id, parent_id, title) не годится — в PostgreSQL
        // NULL в unique-индексе не равен даже самому себе, и все корневые
        // категории проходили бы как неповторяющиеся. COALESCE(parent_id, 0)
        // схлопывает NULL в служебное значение, с которым сравнение работает.
        // Ссылку на user_id в индексе не убираем: он держит уникальность в
        // рамках пользователя, и вместе с cascadeOnDelete на user освобождает
        // имена удалённого владельца.
        DB::statement(
            'CREATE UNIQUE INDEX category_user_parent_title_unique
             ON category (user_id, COALESCE(parent_id, 0), title)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('category');
    }
};
