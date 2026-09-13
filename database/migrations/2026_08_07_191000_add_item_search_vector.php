<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->tsvector('search_vector')
                ->generatedAlwaysAs(
                    DB::raw("setweight(to_tsvector('russian_hunspell'::regconfig, COALESCE(title, ''::text)), 'A'::\"char\") || setweight(to_tsvector('russian_hunspell'::regconfig, COALESCE(title_print, ''::text)), 'B'::\"char\")")
                )
                ->stored();
        });

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
