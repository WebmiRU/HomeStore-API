<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create hunspell dictionary (requires ru_ru.affix + ru_ru.dict in
        // PostgreSQL tsearch_data directory, mounted via docker-compose).
        DB::statement("
            CREATE TEXT SEARCH DICTIONARY IF NOT EXISTS russian_hunspell (
                TEMPLATE = ispell,
                DictFile = ru_ru,
                AffFile = ru_ru,
                StopWords = russian
            )
        ");
        DB::statement("
            CREATE TEXT SEARCH CONFIGURATION IF NOT EXISTS russian_hunspell (
                COPY = russian
            )
        ");
        DB::statement("
            ALTER TEXT SEARCH CONFIGURATION russian_hunspell
                ALTER MAPPING FOR word, hword, hword_part
                WITH russian_hunspell, russian_stem
        ");

        // Switch search_vector columns from 'russian' (Snowball stemmer)
        // to 'russian_hunspell' (ispell/hunspell dictionary) for better
        // inflectional normalization: keeps original word plus adds base form,
        // e.g. коробка → {коробка, коробок} instead of just {коробк}.

        // item
        DB::statement('DROP INDEX IF EXISTS idx_item_search');
        DB::statement('ALTER TABLE item DROP COLUMN IF EXISTS search_vector');
        DB::statement("
            ALTER TABLE item ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian_hunspell', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('russian_hunspell', coalesce(title_print, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_item_search ON item USING GIN (search_vector)');

        // store
        DB::statement('DROP INDEX IF EXISTS idx_store_search');
        DB::statement('ALTER TABLE store DROP COLUMN IF EXISTS search_vector');
        DB::statement("
            ALTER TABLE store ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian_hunspell', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('russian_hunspell', coalesce(title_print, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_store_search ON store USING GIN (search_vector)');

        // label_list
        DB::statement('DROP INDEX IF EXISTS idx_label_list_search');
        DB::statement('ALTER TABLE label_list DROP COLUMN IF EXISTS search_vector');
        DB::statement("
            ALTER TABLE label_list ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian_hunspell', coalesce(title, '')), 'A')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_label_list_search ON label_list USING GIN (search_vector)');
    }

    public function down(): void
    {
        // Revert to Snowball stemmer

        DB::statement('DROP INDEX IF EXISTS idx_item_search');
        DB::statement('ALTER TABLE item DROP COLUMN IF EXISTS search_vector');
        DB::statement("
            ALTER TABLE item ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('russian', coalesce(title_print, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_item_search ON item USING GIN (search_vector)');

        DB::statement('DROP INDEX IF EXISTS idx_store_search');
        DB::statement('ALTER TABLE store DROP COLUMN IF EXISTS search_vector');
        DB::statement("
            ALTER TABLE store ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('russian', coalesce(title_print, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_store_search ON store USING GIN (search_vector)');

        DB::statement('DROP INDEX IF EXISTS idx_label_list_search');
        DB::statement('ALTER TABLE label_list DROP COLUMN IF EXISTS search_vector');
        DB::statement("
            ALTER TABLE label_list ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('russian', coalesce(title, '')), 'A')
            ) STORED
        ");
        DB::statement('CREATE INDEX idx_label_list_search ON label_list USING GIN (search_vector)');
    }
};
