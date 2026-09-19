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
        $prepared = [];

        DB::transaction(function () use ($type, $payload, &$appliedRows, &$prepared): void {
            // Проход 1: проверяем ВСЕ строки и собираем все проблемы разом,
            // чтобы операция не «падала» с одним сообщением по первой же строке.
            $errors = [];

            foreach ($payload as $row) {
                $found = $this->findCode($row['code']);

                if (!$found) {
                    $errors[] = sprintf('Код "%s" не найден или недоступен', $row['code']);
                    continue;
                }

                if ($found->store_id !== null) {
                    $store = $found->store;
                    $errors[] = sprintf(
                        'Хранилище "%s" нельзя %s',
                        $store?->title ?? $row['code'],
                        $type === 'operation.replenish' ? 'пополнить' : 'списать'
                    );
                    continue;
                }

                $item = $found->item;

                if (!$item) {
                    $errors[] = sprintf('Код "%s" не привязан к предмету', $row['code']);
                    continue;
                }

                $delta = (int) $row['quantity'];

                if ($type === 'operation.writeoff') {
                    if ($item->quantity !== null && (int) $item->quantity < $delta) {
                        $errors[] = sprintf(
                            'Недостаточно количества у предмета "%s" (в наличии %d)',
                            $item->title,
                            (int) $item->quantity
                        );
                        continue;
                    }

                    if ($item->quantity === null && $delta > 1) {
                        $errors[] = sprintf('У предмета "%s" один экземпляр', $item->title);
                        continue;
                    }
                }

                $prepared[$row['code']] = ['code' => $found, 'item' => $item, 'delta' => $delta];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'payload' => $errors,
                ]);
            }

            // Проход 2: применение уже проверенных строк.
            foreach ($prepared as $code => $entry) {
                $item = $entry['item'];
                $delta = $entry['delta'];

                if ($item->quantity === null) {
                    // Предмет без количественного учёта (единичный экземпляр):
                    // числится как «один в наличии», после операции переводится
                    // в учитываемое количество.
                    $before = 1;

                    if ($type === 'operation.replenish') {
                        $after = $before + $delta;
                        $item->update(['quantity' => $after]);
                    } else {
                        $after = $before - $delta;
                        $item->update(['quantity' => $after]);
                    }

                    $appliedRows[] = [
                        'code'     => $code,
                        'item_id'  => $item->id,
                        'title'    => $item->title,
                        'delta'    => $delta,
                        'before'   => $before,
                        'after'    => $after,
                        'owner_id' => $item->user_id,
                    ];

                    continue;
                }

                $before = (int) $item->quantity;

                if ($type === 'operation.replenish') {
                    $item->increment('quantity', $delta);
                    $after = $before + $delta;
                } else {
                    $item->decrement('quantity', $delta);
                    $after = $before - $delta;
                }

                $appliedRows[] = [
                    'code'     => $code,
                    'item_id'  => $item->id,
                    'title'    => $item->title,
                    'delta'    => $delta,
                    'before'   => $before,
                    'after'    => $after,
                    'owner_id' => $item->user_id,
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

        return response()->json([
            'type'    => $type,
            'payload' => $payload,
            'rows'    => $appliedRows,
        ]);
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
