<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Store;
use App\Services\AccessService;
use App\Support\CurrentUser;
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
            ->selectRaw("
                'item' as type,
                ts_rank(search_vector, ?) as rank,
                similarity(title, ?) as sim,
                json_build_object(
                    'id', id,
                    'title', title,
                    'title_print', title_print,
                    'store_id', store_id,
                    'created_at', created_at,
                    'updated_at', updated_at
                ) as payload
            ", [$tsquery, $q])
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
                    ->selectRaw("
                        'store' as type,
                        ts_rank(search_vector, ?) as rank,
                        similarity(title, ?) as sim,
                        json_build_object(
                            'id', id,
                            'title', title,
                            'title_print', title_print,
                            'parent_id', parent_id,
                            'created_at', created_at,
                            'updated_at', updated_at
                        ) as payload
                    ", [$tsquery, $q])
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
            ->get()
            ->map(function ($row) {
                $row->payload = json_decode($row->payload);

                $model = $row->type === 'item'
                    ? Item::find($row->payload->id)
                    : Store::find($row->payload->id);

                $rights = $model !== null
                    ? app(AccessService::class)->rightsFor($model)
                    : [];

                $row->payload->rights = $rights;
                $row->payload->is_owner = $model?->user_id !== null
                    && (int) $model->user_id === (int) CurrentUser::id();
                $row->payload->can_edit = in_array('edit', $rights, true);
                $row->payload->can_delete = in_array('delete', $rights, true);

                return $row;
            });

        return response()->json(['data' => $results]);
    }
}
