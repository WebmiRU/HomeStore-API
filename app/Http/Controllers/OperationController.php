<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreOperationRequest;
use App\Models\Code;
use App\Models\Item;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationController extends Controller
{
    public function __construct(private readonly AuditLogService $logs)
    {
    }

    public function store(StoreOperationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = $validated['type'];
        $payload = $validated['payload'];

        $appliedRows = [];

        DB::transaction(function () use ($type, $payload, &$appliedRows): void {
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

                $before = (int) $item->quantity;
                $delta = (int) $row['quantity'];

                if ($type === 'operation.replenish') {
                    $item->increment('quantity', $delta);
                    $after = $before + $delta;
                } else {
                    if ($item->quantity < $delta) {
                        throw ValidationException::withMessages([
                            'payload' => [sprintf('Недостаточно количества у предмета "%s"', $item->title)],
                        ]);
                    }

                    $item->decrement('quantity', $delta);
                    $after = $before - $delta;
                }

                $appliedRows[] = [
                    'code'    => $row['code'],
                    'item_id' => $item->id,
                    'title'   => $item->title,
                    'delta'   => $delta,
                    'before'  => $before,
                    'after'   => $after,
                    'owner_id'=> $item->user_id,
                ];
            }
        });

        // Журнал операций: отдельная запись на каждый предмет (видимость — владелец предмета).
        $action = $type === 'operation.replenish'
            ? AuditAction::OperationReplenish
            : AuditAction::OperationWriteoff;

        foreach ($appliedRows as $appliedRow) {
            $this->logs->record(
                $action,
                'item_id',
                (int) $appliedRow['item_id'],
                (int) ($appliedRow['owner_id'] ?? 0),
                [
                    'code'   => $appliedRow['code'],
                    'title'  => $appliedRow['title'],
                    'delta'  => $appliedRow['delta'],
                    'before' => $appliedRow['before'],
                    'after'  => $appliedRow['after'],
                ],
            );
        }

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
