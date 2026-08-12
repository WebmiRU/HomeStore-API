<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperationRequest;
use App\Models\Code;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationController extends Controller
{
    public function store(StoreOperationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = $validated['type'];
        $payload = $validated['payload'];

        DB::transaction(function () use ($type, $payload): void {
            foreach ($payload as $row) {
                $code = $this->findCode($row['code']);

                if (!$code) {
                    throw ValidationException::withMessages([
                        'payload' => [sprintf('Код "%s" не найден', $row['code'])],
                    ]);
                }

                if ($code->store_id !== null) {
                    $store = $code->store;
                    throw ValidationException::withMessages([
                        'payload' => [sprintf(
                            'Хранилище "%s" нельзя %s',
                            $store?->title ?? $row['code'],
                            $type === 'operation.replenish' ? 'пополнить' : 'списать'
                        )],
                    ]);
                }

                $item = $code->item;

                if (!$item) {
                    throw ValidationException::withMessages([
                        'payload' => [sprintf('Код "%s" не привязан к предмету', $row['code'])],
                    ]);
                }

                if ($item->quantity === null) {
                    throw ValidationException::withMessages([
                        'payload' => [sprintf('У предмета "%s" нет количества', $item->title)],
                    ]);
                }

                if ($type === 'operation.replenish') {
                    $item->increment('quantity', $row['quantity']);
                    continue;
                }

                if ($item->quantity < $row['quantity']) {
                    throw ValidationException::withMessages([
                        'payload' => [sprintf('Недостаточно количества у предмета "%s"', $item->title)],
                    ]);
                }

                $item->decrement('quantity', $row['quantity']);
            }
        });

        return response()->json($validated);
    }

    private function findCode(string $code): ?Code
    {
        $candidates = [$code];

        // «Голый» UUID (32 hex-символа) приводим к дефисному виду для совместимости
        if (strlen($code) === 32 && ctype_xdigit($code)) {
            $lower = strtolower($code);
            $candidates[] = substr($lower, 0, 8) . '-'
                . substr($lower, 8, 4) . '-'
                . substr($lower, 12, 4) . '-'
                . substr($lower, 16, 4) . '-'
                . substr($lower, 20);
        }

        return Code::with(['store', 'item'])
            ->whereIn('code', $candidates)
            ->first();
    }
}
