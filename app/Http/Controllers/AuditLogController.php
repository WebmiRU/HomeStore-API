<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    private const ENTITY_TYPES = [
        'item', 'store', 'warehouse', 'label_preset', 'label_list', 'access_grant', 'user',
    ];

    /**
     * Пагинированный журнал действий. Видимость — только владельцу домена
     * (owner_id = текущий пользователь). Опциональный фильтр по user_id —
     * задел под админские права: администратор видит логи любых владельцев.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()->with('actor');

        $this->applyScope($query, $request);

        return AuditLogResource::collection(
            $query->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    /**
     * Агрегаты для графиков. Скоуп по владельцу, group_by — сущность/действие/день.
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'group_by'    => ['required', 'string', 'in:action,entity,day'],
            'granularity' => ['sometimes', 'string', 'in:hour,day'],
            'date_from'   => ['sometimes', 'date', 'before_or_equal:date_to'],
            'date_to'     => ['sometimes', 'date', 'after_or_equal:date_from'],
            'entity_type' => ['sometimes', 'string', 'in:' . implode(',', self::ENTITY_TYPES)],
            'entity_id'   => ['sometimes', 'integer', 'min:1'],
        ])->validated();

        $groupBy = $validated['group_by'];
        $granularity = $validated['granularity'] ?? 'day';

        $query = AuditLog::query();
        $this->applyScope($query, $request);

        $selects = [];
        $group = [];

        $truncated = $granularity === 'hour' ? 'hour' : 'day';

        // Разбивка по времени (bucket) нужна только для «Активности за период».
        // Для «по действиям»/«по объектам» считаются суммарные итоги за период:
        // иначе каждая строка вида (день, действие) дублирует одни и те же
        // действия и объекты по всем дням периода.
        if ($groupBy === 'day') {
            $selects[] = "date_trunc('" . $truncated . "', created_at) AS bucket";
            $group[] = 'bucket';
        }

        if ($groupBy === 'action') {
            $selects[] = 'action AS key';
            $group[] = 'action';
        } elseif ($groupBy === 'entity') {
            $selects[] = $this->entityCaseExpression().' AS key';
            $group[] = 'key';
        }

        $selects[] = 'COUNT(*) AS count';

        $rows = $query
            ->selectRaw(implode(', ', $selects))
            ->groupBy($group)
            ->get()
            ->map(function ($row) use ($granularity, $groupBy) {
                $bucket = null;
                if ($groupBy === 'day' && $row->bucket !== null) {
                    $bucket = $granularity === 'hour'
                        ? substr((string) $row->bucket, 0, 13).':00'
                        : substr((string) $row->bucket, 0, 10);
                }
                // При group_by=day «ключ» не нужен — это единый ряд активности.
                $key = $groupBy === 'day' ? null : $row->key;

                return [
                    'bucket' => $bucket,
                    'key'    => $key,
                    'count'  => (int) $row->count,
                ];
            });

        // Хронология — по датам; итоги по действиям/объектам — по убыванию.
        $rows = $groupBy === 'day'
            ? $rows->sortBy('bucket')
            : $rows->sortByDesc('count');

        return response()->json(['data' => $rows->values()]);
    }

    /**
     * Снимки количества (остатка) во времени для конкретной сущности.
     * Строятся из лога изменений: item.created / item.updated / operation.*.
     * Итоговые точки уникальны: повторяющиеся значения (например, item.updated
     * следующий сразу за операцией) отбрасываются.
     */
    public function balance(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'entity_type' => ['required', 'string', 'in:' . implode(',', self::ENTITY_TYPES)],
            'entity_id'   => ['required', 'integer', 'min:1'],
            'date_from'   => ['sometimes', 'date', 'before_or_equal:date_to'],
            'date_to'     => ['sometimes', 'date', 'after_or_equal:date_from'],
        ])->validated();

        $query = AuditLog::query();
        $this->applyScope($query, $request);

        $rows = $query
            ->whereIn('action', ['item.created', 'item.updated', 'operation.replenish', 'operation.writeoff'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(2000)
            ->get(['id', 'created_at', 'payload']);

        $points = [];
        $lastQty = null;

        foreach ($rows as $row) {
            $payload = $row->payload ?? [];
            $qty = $payload['after'] ?? $payload['snapshot']['quantity'] ?? null;

            if ($qty === null) {
                continue;
            }

            $qty = (int) $qty;

            if ($qty === $lastQty) {
                continue;
            }

            $lastQty = $qty;
            $points[] = [
                'at'  => (string) $row->created_at,
                'qty' => $qty,
            ];
        }

        return response()->json(['data' => $points]);
    }

    /**
     * Общий скоуп журнала: владелец + фильтры действий/сущности/периода.
     * Используется и списком, и агрегатами, чтобы графики соответствовали таблице.
     */
    private function applyScope(Builder $query, Request $request): void
    {
        $query->where('owner_id', CurrentUser::id());

        foreach (['action', 'user_id'] as $filter) {
            if ($request->filled($filter)) {
                $this->applyFilter($query, $filter, $request->input($filter));
            }
        }

        if ($request->filled('entity_type')) {
            $type = (string) $request->input('entity_type');
            $column = $this->entityTypeToColumn($type);
            $entityId = $request->input('entity_id');

            $query->where(function (Builder $q) use ($column, $type, $entityId) {
                // Durable-ссылка в payload переживает удаление сущности,
                // поэтому ищем и по FK-колонке, и по payload.
                $q->where(function (Builder $q) use ($column, $entityId) {
                    if ($entityId !== null) {
                        $q->where($column, (int) $entityId);
                    } else {
                        $q->where($column, '!=', null);
                    }
                })->orWhere(function (Builder $q) use ($type, $entityId) {
                    // Ключи — константы, экранирование не требуется.
                    $q->whereRaw("payload->>'entity_type' = ?", [$type]);
                    if ($entityId !== null) {
                        $q->whereRaw("payload->>'entity_id' = ?", [(string) $entityId]);
                    }
                });
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
    }

    private function applyFilter(Builder $query, string $name, string $value): void
    {
        if ($name === 'action') {
            $query->where('action', $value);
            return;
        }

        if ($name === 'user_id') {
            // Владелец видит логи своего домена; фильтр по актору в рамках домена.
            $query->where('actor_id', (int) $value);
        }
    }

    private function entityTypeToColumn(string $type): string
    {
        $map = [
            'item'        => 'item_id',
            'store'       => 'store_id',
            'warehouse'   => 'warehouse_id',
            'label_preset' => 'label_preset_id',
            'label_list'  => 'label_list_id',
            'access_grant' => 'access_grant_id',
            'user'        => 'target_user_id',
        ];

        abort_unless(isset($map[$type]), 422, "Неизвестный entity_type: {$type}");

        return $map[$type];
    }

    private function entityCaseExpression(): string
    {
        // durable-ссылка в payload (переживает удаление сущности) имеет приоритет.
        return "COALESCE(payload ->> 'entity_type', CASE "
            . "WHEN item_id IS NOT NULL THEN 'item' "
            . "WHEN store_id IS NOT NULL THEN 'store' "
            . "WHEN warehouse_id IS NOT NULL THEN 'warehouse' "
            . "WHEN label_preset_id IS NOT NULL THEN 'label_preset' "
            . "WHEN label_list_id IS NOT NULL THEN 'label_list' "
            . "WHEN access_grant_id IS NOT NULL THEN 'access_grant' "
            . "WHEN target_user_id IS NOT NULL THEN 'user' "
            . "ELSE 'none' END)";
    }
}