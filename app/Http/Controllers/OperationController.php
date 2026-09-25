<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreOperationRequest;
use App\Models\Code;
use App\Models\Item;
use App\Services\AuditLogService;
use App\Support\CodeFormat;
use App\Support\CurrentUser;
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
                [$storeMatch, $items] = $this->resolveCode($row['code']);

                if ($storeMatch !== null) {
                    $store = $storeMatch->store;
                    $errors[] = sprintf(
                        'Хранилище "%s" нельзя %s',
                        $store?->title ?? $row['code'],
                        $type === 'operation.replenish' ? 'пополнить' : 'списать'
                    );
                    continue;
                }

                if ($items->isEmpty()) {
                    $errors[] = sprintf('Код "%s" не найден или недоступен', $row['code']);
                    continue;
                }

                if ($items->count() > 1) {
                    // Коллизия: одинаковый код у нескольких предметов.
                    // Направляем выбор конкретного предмета на стороне клиента.
                    $itemId = (int) ($row['item_id'] ?? 0);

                    if ($itemId === 0 || !$items->has($itemId)) {
                        $errors[] = sprintf(
                            'Код "%s" привязан к нескольким предметам — укажите конкретный предмет',
                            $row['code']
                        );
                        continue;
                    }

                    $item = $items->get($itemId)->item;
                } else {
                    $item = $items->first()->item;
                }

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

                $prepared[] = ['code' => $row['code'], 'item' => $item, 'delta' => $delta];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'payload' => $errors,
                ]);
            }

            // Проход 2: применение уже проверенных строк.
            foreach ($prepared as $entry) {
                $item = $entry['item'];
                $delta = $entry['delta'];
                $code = $entry['code'];

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
                    // В журнале дельта знаковая: списание — отрицательная.
                    'delta'  => $appliedRow['after'] - $appliedRow['before'],
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

    /**
     * Разрешение кода: первое store-совпадение (скан кода хранилища) и
     * коллекция предметов, дедуплицированная по item_id, в порядке
     * «свои сначала, новые выше» (см. Code::scopeMatchesFor).
     *
     * @return array{0: ?Code, 1: \Illuminate\Support\Collection<int, Code>}
     */
    private function resolveCode(string $code): array
    {
        $codes = Code::with(['store', 'item'])
            ->whereIn('code', $this->codeCandidates($code))
            ->matchesFor((int) CurrentUser::id())
            ->get();

        $storeMatch = $codes->first(fn (Code $row) => $row->store_id !== null);

        $items = collect();

        foreach ($codes as $row) {
            if ($row->item_id === null || $row->item === null) {
                continue;
            }

            if (!$items->has($row->item_id)) {
                $items->put($row->item_id, $row);
            }
        }

        return [$storeMatch, $items];
    }

    private function codeCandidates(string $code): array
    {
        return CodeFormat::candidates($code);
    }
}
