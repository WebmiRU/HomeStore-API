<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('code', function (Blueprint $table) {
            $table->unsignedBigInteger('label_list_id')->nullable()->index();
        });

        Schema::table('code', function (Blueprint $table) {
            // SET NULL, а не CASCADE: наклейки физически наклеены, удаление
            // набора не должно уничтожать сами коды — они остаются свободными.
            $table->foreign('label_list_id')
                ->references('id')
                ->on('label_list')
                ->nullOnDelete();
        });

        // Уникальность кода гарантируем только внутри сгенерированных наборов.
        // Глобальный уникальный индекс был снят миграцией
        // 2026_09_16_220002 (один штрихкод на много экземпляров товара),
        // и возвращать его нельзя — зато внутри набора дубль недопустим.
        DB::statement(
            'CREATE UNIQUE INDEX code_label_list_code_unique ON code (code) WHERE label_list_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS code_label_list_code_unique');

        Schema::table('code', function (Blueprint $table) {
            $table->dropForeign(['label_list_id']);
            $table->dropColumn('label_list_id');
        });
    }
};
