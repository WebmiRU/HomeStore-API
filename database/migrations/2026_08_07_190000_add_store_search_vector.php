<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate search_vector as a proper generated column
        // (Laravel's tsvector() builder doesn't properly handle generated columns)
        DB::statement(
            "ALTER TABLE store DROP COLUMN IF EXISTS search_vector"
        );
        DB::statement(
            "ALTER TABLE store ADD COLUMN search_vector tsvector NOT NULL GENERATED ALWAYS AS ("
            . "setweight(to_tsvector('russian_hunspell'::regconfig, COALESCE(title, ''::text)), 'A'::char) "
            . "|| setweight(to_tsvector('russian_hunspell'::regconfig, COALESCE(title_print, ''::text)), 'B'::char)"
            . ") STORED"
        );

        DB::statement('CREATE INDEX idx_store_search ON store USING gin (search_vector)');
    }

    public function down(): void
    {
        Schema::table('store', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });

        DB::statement('DROP INDEX IF EXISTS idx_store_search');
    }
};
