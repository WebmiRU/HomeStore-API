<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_root_id')->nullable()->index();
        });

        // Заполняем warehouse_root_id рекурсивно из корневых хранилищ.
        DB::statement('
            WITH RECURSIVE store_root AS (
                SELECT id,
                       COALESCE(warehouse_id, NULL) AS warehouse_root_id,
                       parent_id
                FROM store
                WHERE warehouse_id IS NOT NULL

                UNION ALL

                SELECT s.id,
                       sr.warehouse_root_id,
                       s.parent_id
                FROM store s
                JOIN store_root sr ON s.parent_id = sr.id
            )
            UPDATE store
            SET warehouse_root_id = sr.warehouse_root_id
            FROM store_root sr
            WHERE store.id = sr.id
              AND store.warehouse_root_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('store', function (Blueprint $table) {
            $table->dropColumn('warehouse_root_id');
        });
    }
};
