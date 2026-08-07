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

        // Build prefix tsquery with OR semantics: "короб болт" -> "короб:* | болт:*"
        $tsquery = DB::selectOne(
            "SELECT to_tsquery('russian', ?) AS q",
            [implode(':* | ', explode(' ', $q)) . ':*']
        )->q;

        $words = explode(' ', $q);

        $results = DB::query()
            ->selectRaw("'item' as type, id, title, title_print, ts_rank(search_vector, ?) as rank, similarity(title, ?) as sim", [$tsquery, $q])
            ->from('item')
            ->where(function ($query) use ($tsquery, $q, $words) {
                $query
                    ->whereRaw('search_vector @@ ?', [$tsquery])
                    ->orWhereRaw('similarity(title, ?) > 0.2', [$q]);
                foreach ($words as $word) {
                    $query->orWhereRaw('title ILIKE ?', ['%' . $word . '%']);
                }
            })
            ->unionAll(
                DB::query()
                    ->selectRaw("'store' as type, id, title, title_print, ts_rank(search_vector, ?) as rank, similarity(title, ?) as sim", [$tsquery, $q])
                    ->from('store')
                    ->where(function ($query) use ($tsquery, $q, $words) {
                        $query
                            ->whereRaw('search_vector @@ ?', [$tsquery])
                            ->orWhereRaw('similarity(title, ?) > 0.2', [$q]);
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
