<?php

namespace App\Http\Controllers;

use App\Http\Resources\ImageResource;
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

        // Punctuation-only queries would crash to_tsquery → return nothing.
        if (!preg_match('/[\p{L}\p{N}]/u', $q)) {
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

        // Morphological roots query (stem-like matching): reduces derived forms
        // to a shared root (оцинковка/оцинкованный/оцинк → оцинк, цинк),
        // so any inflected/derived form matches the same items.
        $morphQuery = DB::selectOne(
            "SELECT public.morph_tsquery(?) AS q",
            [$q]
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

        $userId = (int) CurrentUser::id();

        $itemQuery = Item::query()
            ->withoutGlobalScope('accessibleByUser')
            ->selectRaw("
                'item' as type,
                GREATEST(ts_rank(search_vector, ?), ts_rank(morph_vector, ?)) as rank,
                similarity(title, ?) as sim,
                json_build_object(
                    'id', id,
                    'title', title,
                    'title_print', title_print,
                    'store_id', store_id,
                    'created_at', created_at,
                    'updated_at', updated_at
                ) as payload
            ", [$tsquery, $morphQuery, $q])
            ->where(function ($query) use ($tsquery, $morphQuery, $q, $words, $useSimilarity, $allRecognized) {
                $query
                    ->whereRaw('search_vector @@ ?', [$tsquery])
                    ->orWhereRaw('morph_vector @@ ?', [$morphQuery]);
                if ($useSimilarity) {
                    if ($allRecognized) {
                        $query->orWhereRaw(
                            "similarity(title, ?) > 0.15 AND title ILIKE '%' || left(?, 5) || '%'",
                            [$q, $q]
                        );
                    } else {
                        // Unknown word (likely typo): require a real contiguous
                        // substring match (word_similarity), not just a few shared
                        // trigrams — e.g. "прессшайбой" ≠ "предмет 1" (0.25),
                        // but "гайкн" ≈ "Гайка" (0.67).
                        $query->orWhereRaw(
                            'similarity(title, ?) > 0.15 AND word_similarity(?, title) > 0.4',
                            [$q, $q]
                        );
                    }
                }
                foreach ($words as $word) {
                    $query->orWhereRaw('title ILIKE ?', ['%' . $word . '%']);
                }
            })
            ->accessibleTo($userId)
            ->toBase();

        $storeQuery = Store::query()
            ->withoutGlobalScope('accessibleByUser')
            ->selectRaw("
                'store' as type,
                GREATEST(ts_rank(search_vector, ?), ts_rank(morph_vector, ?)) as rank,
                similarity(title, ?) as sim,
                json_build_object(
                    'id', id,
                    'title', title,
                    'title_print', title_print,
                    'parent_id', parent_id,
                    'created_at', created_at,
                    'updated_at', updated_at
                ) as payload
            ", [$tsquery, $morphQuery, $q])
            ->where(function ($query) use ($tsquery, $morphQuery, $q, $words, $useSimilarity, $allRecognized) {
                $query
                    ->whereRaw('search_vector @@ ?', [$tsquery])
                    ->orWhereRaw('morph_vector @@ ?', [$morphQuery]);
                if ($useSimilarity) {
                    if ($allRecognized) {
                        $query->orWhereRaw(
                            "similarity(title, ?) > 0.15 AND title ILIKE '%' || left(?, 5) || '%'",
                            [$q, $q]
                        );
                    } else {
                        $query->orWhereRaw(
                            'similarity(title, ?) > 0.15 AND word_similarity(?, title) > 0.4',
                            [$q, $q]
                        );
                    }
                }
                foreach ($words as $word) {
                    $query->orWhereRaw('title ILIKE ?', ['%' . $word . '%']);
                }
            })
            ->accessibleTo($userId)
            ->toBase();

        $results = $itemQuery
            ->unionAll($storeQuery)
            ->orderBy('rank', 'desc')
            ->orderBy('sim', 'desc')
            ->get()
            ->map(function ($row) {
                $row->payload = json_decode($row->payload);

                $model = $row->type === 'item'
                    ? Item::with(['images', 'code'])->find($row->payload->id)
                    : Store::with(['images', 'code'])->find($row->payload->id);

                $rights = $model !== null
                    ? app(AccessService::class)->rightsFor($model)
                    : [];

                $row->payload->code = $model?->code?->code;
                $row->payload->rights = $rights;
                $row->payload->is_owner = $model?->user_id !== null
                    && (int) $model->user_id === (int) CurrentUser::id();
                $row->payload->can_edit = in_array('edit', $rights, true);
                $row->payload->can_delete = in_array('delete', $rights, true);
                $row->payload->images = $model !== null
                    ? ImageResource::collection($model->images)->resolve()
                    : [];

                return $row;
            });

        return response()->json(['data' => $results]);
    }
}
