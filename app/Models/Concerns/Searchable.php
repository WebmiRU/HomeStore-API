<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait Searchable
{
    /**
     * Full-text search using PostgreSQL tsvector with prefix matching,
     * falling back to trigram similarity when the Russian stemmer produces
     * different lexemes for related words (e.g. коробочка vs коробка).
     *
     * The model must have a "search_vector" generated column.
     * Requires pg_trgm extension enabled.
     */
    public function scopeSearch(Builder $query, string $q): Builder
    {
        if (blank($q)) {
            return $query->whereRaw('1 = 0');
        }

        // Build prefix tsquery with OR semantics: "короб болт" → "короб:* | болт:*"
        $tsquery = DB::selectOne(
            "SELECT to_tsquery('russian', ?) AS q",
            [implode(':* | ', explode(' ', $q)) . ':*']
        )->q;

        $table = $query->getModel()->getTable();

        return $query
            ->where(function (Builder $query) use ($tsquery, $q, $table) {
                $query
                    // Primary: FTS with prefix matching
                    ->whereRaw("{$table}.search_vector @@ ?", [$tsquery])
                    // Fallback: trigram similarity on raw title
                    ->orWhereRaw("similarity({$table}.title, ?) > 0.2", [$q]);
            })
            // FTS matches rank highest, similarity-only matches come after
            ->orderByRaw("ts_rank({$table}.search_vector, ?) DESC NULLS LAST", [$tsquery])
            ->orderByRaw("similarity({$table}.title, ?) DESC", [$q]);
    }
}
