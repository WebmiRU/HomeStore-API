<?php

namespace App\Http\Controllers;

use App\Http\Resources\CodeResource;
use App\Models\Code;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class CodeController extends Controller
{
    public function index()
    {
        return Uuid::uuid7()->toString();
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));

        if (strlen($q) < 8 || strlen($q) > 256) {
            return response()->json(['error' => 'Invalid code length'], 400);
        }

        $candidates = [$q];

        // «Голый» UUID (32 hex-символа) приводим к дефисному виду для совместимости
        if (strlen($q) === 32 && ctype_xdigit($q)) {
            $lower = strtolower($q);
            $candidates[] = substr($lower, 0, 8) . '-'
                . substr($lower, 8, 4) . '-'
                . substr($lower, 12, 4) . '-'
                . substr($lower, 16, 4) . '-'
                . substr($lower, 20);
        }

        $matches = Code::with(['store.parent', 'store.images', 'item.store.parent', 'item.images', 'user'])
            ->whereIn('code', $candidates)
            ->matchesFor((int) CurrentUser::id())
            ->get();

        $storeMatch = $matches->first(fn ($row) => $row->store_id !== null);

        if ($storeMatch !== null) {
            return new CodeResource($storeMatch);
        }

        $items = collect();
        foreach ($matches as $row) {
            if ($row->item_id === null || $row->item === null) {
                continue;
            }
            if (!$items->has($row->item_id)) {
                $items->put($row->item_id, $row);
            }
        }

        if ($items->isEmpty()) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($items->count() === 1) {
            return new CodeResource($items->first());
        }

        // Коллизия: одинаковый код у нескольких доступных предметов —
        // отдаём все варианты (свои сначала, новые выше).
        return response()->json([
            'code'      => trim((string) $request->query('q')),
            'ambiguous' => true,
            'matches'   => CodeResource::collection($items->values())
                ->resolve($request),
        ]);
    }
}
