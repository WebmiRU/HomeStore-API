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
            $table->tsvector('search_vector')
                ->generatedAlwaysAs(
                    DB::raw("setweight(to_tsvector('russian_hunspell'::regconfig, COALESCE(title, ''::text)), 'A'::\"char\") || setweight(to_tsvector('russian_hunspell'::regconfig, COALESCE(title_print, ''::text)), 'B'::\"char\")")
                )
                ->stored();
        });

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
