<?php

namespace App\Http\Controllers;

use App\Http\Resources\CodeResource;
use App\Models\Code;
use App\Support\CodeFormat;
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

        $matches = Code::with(['store.parent', 'store.images', 'item.store.parent', 'item.images', 'user', 'labelList'])
            ->whereIn('code', CodeFormat::candidates($q))
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

        if ($items->count() === 1) {
            return new CodeResource($items->first());
        }

        if ($items->isNotEmpty()) {
            // Коллизия: одинаковый код у нескольких доступных предметов —
            // отдаём все варианты (свои сначала, новые выше).
            return response()->json([
                'code'      => trim((string) $request->query('q')),
                'ambiguous' => true,
                'matches'   => CodeResource::collection($items->values())
                    ->resolve($request),
            ]);
        }

        // Код есть, но не привязан ни к предмету, ни к хранилищу — это
        // безымянная этикетка из сгенерированного набора. Отдаём её
        // отдельным ответом, а не 404: отсутствие привязки здесь не
        // ошибка, а нормальный этап жизни наклейки (напечатали, ещё не
        // использовали). Проверка по свойствам, а не по флагу: свободный
        // код и есть безымянная этикетка.
        $blank = $matches->first(fn ($row) => $row->item_id === null && $row->store_id === null);

        if ($blank !== null) {
            return response()->json([
                'code'      => trim((string) $request->query('q')),
                'blank'     => true,
                'label_set' => $blank->labelList !== null
                    ? [
                        'id'    => $blank->labelList->id,
                        'title' => $blank->labelList->title,
                    ]
                    : null,
            ]);
        }

        return response()->json(['error' => 'Not found'], 404);
    }
}
