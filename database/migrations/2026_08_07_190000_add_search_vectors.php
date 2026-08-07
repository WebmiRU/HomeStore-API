<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // item: search on title (weight A) + title_print (weight B)
        DB::statement("
            ALTER TABLE item ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('russian', coalesce(title_print, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_item_search ON item USING GIN (search_vector)');

        // store: search on title (weight A) + title_print (weight B)
        DB::statement("
            ALTER TABLE store ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('russian', coalesce(title_print, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_store_search ON store USING GIN (search_vector)');

        // label_list: search on title (weight A)
        DB::statement("
            ALTER TABLE label_list ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian', coalesce(title, '')), 'A')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_label_list_search ON label_list USING GIN (search_vector)');
    }

    public function down(): void
    {
        Schema::table('label_list', function ($table) {
            $table->dropIndex('idx_label_list_search');
            $table->dropColumn('search_vector');
        });

        Schema::table('store', function ($table) {
            $table->dropIndex('idx_store_search');
            $table->dropColumn('search_vector');
        });

        Schema::table('item', function ($table) {
            $table->dropIndex('idx_item_search');
            $table->dropColumn('search_vector');
        });
    }
};
