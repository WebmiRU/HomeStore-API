<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Порядок кодов предмета задаётся полем sort, а не порядком id.
        //
        // На id опираться нельзя: код, заведённый позже, получает больший id и
        // в списке оказывался бы в конце, хотя в форме он стоит выше. Иначе
        // главным (верхним) кодом оказывался бы тот, который случайно дольше
        // всех пролежал в базе, а не тот, который человек поставил первым.
        //
        // Имя и смысл — те же, что у item_property.sort: это оба случая
        // «у сущности несколько строк, и нужен заданный человеком порядок».
        Schema::table('code', function (Blueprint $table) {
            $table->integer('sort')->default(0);
        });

        // Бэкфилл до добавления индекса и до ограничения: у каждого
        // существующего предмета коды должны получить разные sort, иначе
        // главный код определить будет нечем.
        DB::statement(<<<'SQL'
            UPDATE "code" AS c
            SET "sort" = numbered.row_number - 1
            FROM (
                SELECT "id", ROW_NUMBER() OVER (PARTITION BY "item_id" ORDER BY "id") AS row_number
                FROM "code"
                WHERE "item_id" IS NOT NULL
            ) AS numbered
            WHERE c."id" = numbered."id"
        SQL);

        DB::statement(<<<'SQL'
            UPDATE "code" AS c
            SET "sort" = numbered.row_number - 1
            FROM (
                SELECT "id", ROW_NUMBER() OVER (PARTITION BY "store_id" ORDER BY "id") AS row_number
                FROM "code"
                WHERE "store_id" IS NOT NULL
            ) AS numbered
            WHERE c."id" = numbered."id"
        SQL);

        // Порядок всегда читается вместе с владельцем: (item_id, sort).
        DB::statement('CREATE INDEX code_item_sort_index ON "code" ("item_id", "sort")');
        DB::statement('CREATE INDEX code_store_sort_index ON "code" ("store_id", "sort")');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS code_store_sort_index');
        DB::statement('DROP INDEX IF EXISTS code_item_sort_index');

        Schema::table('code', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
};
