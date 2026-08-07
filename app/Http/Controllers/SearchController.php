<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if ($q === '') {
            return response()->json(['data' => []]);
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

        $useSimilarity = mb_strlen($q) >= 6;

        // Check if ALL query words are recognized by hunspell.
        // If a word is not in the dictionary, it's likely a typo
        // → use pure similarity for typo tolerance.
        $allRecognized = DB::selectOne(
            "SELECT bool_and(ts_lexize('russian_hunspell', word) IS NOT NULL) AS ok
             FROM unnest(?::text[]) AS word",
            ['{' . implode(',', $words) . '}']
        )->ok;

        $results = DB::query()
            ->selectRaw("'item' as type, id, title, title_print, ts_rank(search_vector, ?) as rank, similarity(title, ?) as sim", [$tsquery, $q])
            ->from('item')
            ->where(function ($query) use ($tsquery, $q, $words, $useSimilarity, $allRecognized) {
                $query
                    ->whereRaw('search_vector @@ ?', [$tsquery]);
                if ($useSimilarity) {
                    if ($allRecognized) {
                        $query->orWhereRaw(
                            "similarity(title, ?) > 0.15 AND title ILIKE '%' || left(?, 5) || '%'",
                            [$q, $q]
                        );
                    } else {
                        $query->orWhereRaw('similarity(title, ?) > 0.15', [$q]);
                    }
                }
                foreach ($words as $word) {
                    $query->orWhereRaw('title ILIKE ?', ['%' . $word . '%']);
                }
            })
            ->unionAll(
                DB::query()
                    ->selectRaw("'store' as type, id, title, title_print, ts_rank(search_vector, ?) as rank, similarity(title, ?) as sim", [$tsquery, $q])
                    ->from('store')
                    ->where(function ($query) use ($tsquery, $q, $words, $useSimilarity, $allRecognized) {
                        $query
                            ->whereRaw('search_vector @@ ?', [$tsquery]);
                        if ($useSimilarity) {
                            if ($allRecognized) {
                                $query->orWhereRaw(
                                    "similarity(title, ?) > 0.15 AND title ILIKE '%' || left(?, 5) || '%'",
                                    [$q, $q]
                                );
                            } else {
                                $query->orWhereRaw('similarity(title, ?) > 0.15', [$q]);
                            }
                        }
                        foreach ($words as $word) {
                            $query->orWhereRaw('title ILIKE ?', ['%' . $word . '%']);
                        }
                    })
            )
            ->orderBy('rank', 'desc')
            ->orderBy('sim', 'desc')
            ->get();

        return response()->json(['data' => $results]);
    }
}
