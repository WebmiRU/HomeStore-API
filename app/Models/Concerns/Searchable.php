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

        $table = $query->getModel()->getTable();

        return $query
            ->where(function (Builder $query) use ($tsquery, $q, $table, $words) {
                $query
                    // Primary: FTS with prefix matching (hunspell dictionary)
                    ->whereRaw("{$table}.search_vector @@ ?", [$tsquery]);
                // Fallback: trigram similarity (only for queries ≥ 6 chars)
                if (mb_strlen($q) >= 6) {
                    $query->orWhereRaw("similarity({$table}.title, ?) > 0.15", [$q]);
                }
                // Substring fallback: for short queries that trigrams miss
                foreach ($words as $word) {
                    $query->orWhereRaw("{$table}.title ILIKE ?", ['%' . $word . '%']);
                }
            })
            // FTS matches rank highest, similarity-only matches come after
            ->orderByRaw("ts_rank({$table}.search_vector, ?) DESC NULLS LAST", [$tsquery])
            ->orderByRaw("similarity({$table}.title, ?) DESC", [$q]);
    }
}
