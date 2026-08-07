<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enable pg_trgm extension for trigram similarity matching.
        // This provides a fallback when the Russian stemmer produces
        // different lexemes for related words (e.g. коробочка vs коробка).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // GIN indexes with trigram operators for fast similarity() lookups.
        DB::statement('CREATE INDEX idx_item_title_trgm ON item USING GIN (title gin_trgm_ops)');
        DB::statement('CREATE INDEX idx_store_title_trgm ON store USING GIN (title gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::table('item', function ($table) {
            $table->dropIndex('idx_item_title_trgm');
        });

        Schema::table('store', function ($table) {
            $table->dropIndex('idx_store_title_trgm');
        });
    }
};
