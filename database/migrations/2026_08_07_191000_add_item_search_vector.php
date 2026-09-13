<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE item DROP COLUMN IF EXISTS search_vector"
        );
        DB::statement(
            "ALTER TABLE item ADD COLUMN search_vector tsvector NOT NULL GENERATED ALWAYS AS ("
            . "setweight(to_tsvector('russian_hunspell'::regconfig, COALESCE(title, ''::text)), 'A'::char) "
            . "|| setweight(to_tsvector('russian_hunspell'::regconfig, COALESCE(title_print, ''::text)), 'B'::char)"
            . ") STORED"
        );

        DB::statement('CREATE INDEX idx_item_search ON item USING gin (search_vector)');
    }

    public function down(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });

        DB::statement('DROP INDEX IF EXISTS idx_item_search');
    }
};
