<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait Searchable
{
    /**
     * Full-text search using PostgreSQL tsvector with hunspell dictionary,
     * falling back to trigram similarity when words have different base forms
     * (e.g. цинковый vs оцинкованный),
     * and ILIKE substring matching for short queries (e.g. цинк in оцинкованный).
     *
     * The model must have a "search_vector" generated column.
     * Requires pg_trgm extension and russian_hunspell dictionary.
     */
    public function scopeSearch(Builder $query, string $q): Builder
    {
        if (blank($q)) {
            return $query->whereRaw('1 = 0');
        }

        $words = explode(' ', $q);

        // Build tsquery: prefix (:*) only for words ≥ 5 chars to avoid
        // short stems matching unrelated words (e.g. "кора" matches "коробка").
        $tsqueryParts = array_map(
            fn(string $w): string => mb_strlen($w) >= 5 ? $w . ':*' : $w,
            $words,
        );
        $tsquery = DB::selectOne(
            "SELECT to_tsquery('russian_hunspell', ?) AS q",
            [implode(' | ', $tsqueryParts)]
        )->q;

        // Morphological roots query: reduces derived/inflected forms to shared
        // roots (оцинковка/оцинкованный/оцинк → оцинк/цинк), stored in the
        // "morph_vector" generated column.
        $morphQuery = DB::selectOne(
            "SELECT public.morph_tsquery(?) AS q",
            [$q]
        )->q;

        // Check if ALL query words are recognized by hunspell.
        // If a word is not in the dictionary, it's likely a typo
        // → use pure similarity for typo tolerance.
        $allRecognized = DB::selectOne(
            "SELECT bool_and(ts_lexize('russian_hunspell', word) IS NOT NULL) AS ok
             FROM unnest(?::text[]) AS word",
            ['{' . implode(',', $words) . '}']
        )->ok;

        $table = $query->getModel()->getTable();

        return $query
            ->where(function (Builder $query) use ($tsquery, $morphQuery, $q, $table, $words, $allRecognized) {
                $query
                    // Primary: FTS with prefix matching (hunspell dictionary)
                    ->whereRaw("{$table}.search_vector @@ ?", [$tsquery])
                    // Morphological roots: unifies derived forms (оцинк/оцинковка/оцинкованный)
                    ->orWhereRaw("{$table}.morph_vector @@ ?", [$morphQuery]);
                // Similarity fallback for derivational variants and typos
                if (mb_strlen($q) >= 6) {
                    if ($allRecognized) {
                        // Valid words: require ≥5 char common substring to avoid
                        // false positives (e.g. "корочка" vs "коробка" share only "кор"=3).
                        $query->orWhereRaw(
                            "similarity({$table}.title, ?) > 0.15 AND {$table}.title ILIKE '%' || left(?, 5) || '%'",
                            [$q, $q]
                        );
                    } else {
                        // Unknown word (likely typo): require a real contiguous
                        // substring match (word_similarity), not just a few shared
                        // trigrams — e.g. "прессшайбой" ≠ "предмет 1" (0.25),
                        // but "гайкн" ≈ "Гайка" (0.67).
                        $query->orWhereRaw(
                            "similarity({$table}.title, ?) > 0.15 AND word_similarity(?, {$table}.title) > 0.4",
                            [$q, $q]
                        );
                    }
                }
                // Substring fallback: for short queries that trigrams miss
                foreach ($words as $word) {
                    $query->orWhereRaw("{$table}.title ILIKE ?", ['%' . $word . '%']);
                }
            })
            // FTS/morph matches rank highest, similarity-only matches come after
            ->orderByRaw("GREATEST(ts_rank({$table}.search_vector, ?), ts_rank({$table}.morph_vector, ?)) DESC NULLS LAST", [$tsquery, $morphQuery])
            ->orderByRaw("similarity({$table}.title, ?) DESC", [$q]);
    }
}
