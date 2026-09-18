<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
        CREATE OR REPLACE FUNCTION public.morph_root_vector(v text)
        RETURNS tsvector
        LANGUAGE plpgsql IMMUTABLE STRICT
        AS $fn$
        DECLARE
          suffixes text[] := ARRAY[
            'ованный','ованная','ованное','ованные',
            'еванный','еванная','еванное','еванные',
            'анный','анная','анное','анные',
            'янный','янная','янное','янные',
            'енный','енная','енное','енные',
            'ующий','ующая','ующее','ующие',
            'авший','авшая','авшее','авшие',
            'явший','явшая','явшее','явшие',
            'вший','вшая','вшее','вшие',
            'вшись','ший','щая','щее','щие',
            'овый','овая','овое','овые',
            'овка','ование','ость','есть','ство','ация','яция','ение','ание','ота','его',
            'ый','ая','ое','ые','ий','ия','ие','ую','юю','ой','их','ым','ом','ах','ям','ами','ями',
            'овать','ывать','ать','ять','еть','ить',
            'ова','ева',
            'ер','ик','чик','щик','ка','ок','ек',
            'ов','ев','ин','ын',
            'ь','а','я','е','у','ю','ы','и','о','ё','й'
          ];
          prefixes text[] := ARRAY['о','об','обо','у','в','во','вы','на','за','до','по','при','про','пред','пере','от','ото','над','под','подо','с','со','из','изо','раз','рас','вз','без'];
          word text;
          tokens text[] := '{}'::text[];
          cur text;
          stem text;
          i int;
          j int;
          c int;
          uniq text[] := '{}'::text[];
        BEGIN
          FOR word IN SELECT x FROM regexp_split_to_table(lower(v), '[^а-яёa-z0-9]+') x WHERE length(x) > 0
          LOOP
            IF word ~ '[0-9]' OR length(word) < 3 THEN
              tokens := tokens || word;
              CONTINUE;
            END IF;
            cur := word;
            tokens := tokens || cur;
            FOR c IN 1..4 LOOP
              stem := NULL;
              FOR i IN 1..array_length(suffixes,1) LOOP
                IF length(cur) > length(suffixes[i]) AND right(cur, length(suffixes[i])) = suffixes[i] AND length(cur) - length(suffixes[i]) >= 3 THEN
                  stem := left(cur, length(cur) - length(suffixes[i]));
                  EXIT;
                END IF;
              END LOOP;
              IF stem IS NULL THEN EXIT; END IF;
              cur := stem;
              tokens := tokens || cur;
              FOR j IN 1..array_length(prefixes,1) LOOP
                IF left(cur, length(prefixes[j])) = prefixes[j] AND length(cur) - length(prefixes[j]) >= 3 THEN
                  tokens := tokens || substr(cur, length(prefixes[j]) + 1);
                END IF;
              END LOOP;
            END LOOP;
          END LOOP;
          SELECT array_agg(DISTINCT x) INTO uniq FROM unnest(tokens) x;
          RETURN to_tsvector('simple'::regconfig, array_to_string(uniq, ' '));
        END
        $fn$;
        SQL);

        DB::statement(<<<'SQL'
        CREATE OR REPLACE FUNCTION public.morph_tsquery(v text)
        RETURNS tsquery
        LANGUAGE sql IMMUTABLE STRICT
        AS $$
          SELECT to_tsquery('simple', array_to_string(tsvector_to_array(public.morph_root_vector(v)), ' | '))
        $$;
        SQL);

        $sql = 'ALTER TABLE item ADD COLUMN IF NOT EXISTS morph_vector tsvector '
            . 'GENERATED ALWAYS AS (public.morph_root_vector(COALESCE(title, \'\') || \' \' || COALESCE(title_print, \'\'))) STORED';
        DB::statement($sql);
        DB::statement('CREATE INDEX IF NOT EXISTS idx_item_morph ON item USING gin (morph_vector)');

        $sql = 'ALTER TABLE store ADD COLUMN IF NOT EXISTS morph_vector tsvector '
            . 'GENERATED ALWAYS AS (public.morph_root_vector(COALESCE(title, \'\') || \' \' || COALESCE(title_print, \'\'))) STORED';
        DB::statement($sql);
        DB::statement('CREATE INDEX IF NOT EXISTS idx_store_morph ON store USING gin (morph_vector)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_item_morph');
        DB::statement('ALTER TABLE item DROP COLUMN IF EXISTS morph_vector');
        DB::statement('DROP INDEX IF EXISTS idx_store_morph');
        DB::statement('ALTER TABLE store DROP COLUMN IF EXISTS morph_vector');
        DB::statement('DROP FUNCTION IF EXISTS public.morph_tsquery(text)');
        DB::statement('DROP FUNCTION IF EXISTS public.morph_root_vector(text)');
    }
};