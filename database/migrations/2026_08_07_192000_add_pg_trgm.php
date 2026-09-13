<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX idx_store_title_trgm ON store USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX idx_item_title_trgm ON item USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_store_title_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_item_title_trgm');
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
};
