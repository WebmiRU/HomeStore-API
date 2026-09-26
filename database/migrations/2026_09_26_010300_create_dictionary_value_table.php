<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Своей user_id у значения нет намеренно: оно достижимо только через
        // справочник, а тот отсекается скоупом владельца. Дублировать
        // владельца в каждой строке — значит разрешить ему рассинхронизироваться
        // с родителем и завести «свои» значения в чужом справочнике.
        Schema::create('dictionary_value', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dictionary_id');
            $table->text('title');
            $table->timestamps();

            $table->unique(['dictionary_id', 'title'], 'dictionary_value_dictionary_id_title_unique');

            // cascadeOnDelete: удалённое значение справочника обязано унести
            // и item_property, ссылающиеся на него, — иначе осталась бы строка
            // со ссылкой на несуществующий справочник.
            $table->foreign('dictionary_id')
                ->references('id')
                ->on('dictionary')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_value');
    }
};
