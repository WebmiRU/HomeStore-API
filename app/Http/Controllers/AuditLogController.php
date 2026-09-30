<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Property;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    private const ENTITY_TYPES = [
        'item', 'store', 'warehouse', 'label_preset', 'label_list', 'access_grant', 'user',
        'category', 'property', 'property_group', 'dictionary', 'dictionary_value', 'unit',
        'vendor',
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
     * Агрегаты для графиков. Скоуп по владельцу.
     *
     * group_by (можно комбинировать через запятую):
     *   day                 — активность по времени (bucket = день/час);
     *   action | entity     — суммарные итоги за период по действиям/объектам;
     *   day,action          — матрица «время × действие» для stacked-графиков;
     *   day,entity          — матрица «время × объект».
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'group_by'    => ['required', 'string'],
            'granularity' => ['sometimes', 'string', 'in:hour,day'],
            'date_from'   => ['sometimes', 'date', 'before_or_equal:date_to'],
            'date_to'     => ['sometimes', 'date', 'after_or_equal:date_from'],
            'entity_type' => ['sometimes', 'string', 'in:' . implode(',', self::ENTITY_TYPES)],
            'entity_id'   => ['sometimes', 'integer', 'min:1'],
            'tz_offset'   => ['sometimes', 'integer', 'between:-840,840'],
        ])->validated();

        $parts = array_values(array_unique(array_map('trim', explode(',', (string) $validated['group_by']))));
        sort($parts);

        abort_if($parts === [], 422, __('group_by не может быть пустым'));

        foreach ($parts as $part) {
            abort_unless(in_array($part, ['action', 'entity', 'day'], true), 422, "Неизвестная группировка: {$part}");
        }
        // Нельзя группировать одновременно по действиям и по объектам.
        abort_if(
            in_array('action', $parts, true) && in_array('entity', $parts, true),
            422,
            __('Нельзя группировать одновременно по действиям и по объектам')
        );

        $byDay = in_array('day', $parts, true);
        $byAction = in_array('action', $parts, true);
        $byKey = $byAction || in_array('entity', $parts, true);

        $granularity = $validated['granularity'] ?? 'day';

        // Сдвиг часового пояса пользователя (в минутах) для корректных локальных
        // бакетов дней/часов: created_at хранится в UTC, а пользователь может
        // жить в другой зоне (например +03:00). Сдвиг применяется до date_trunc,
        // чтобы границы дня/часа совпадали с локальным календарём.
        $tzOffset = (int) ($validated['tz_offset'] ?? 0);
        $tzShift = $tzOffset === 0
            ? 'created_at'
            : 'created_at + make_interval(mins => ' . $tzOffset . ')';

        $query = AuditLog::query();
        $this->applyScope($query, $request);

        $selects = [];
        $group = [];

        // Бакет по времени — для «Активности за период» (group_by=day) и для
        // матриц «время × действие/объект». Агрегаты без дня (action/entity)
        // дают суммарные итоги за выбранный период.
        if ($byDay) {
            $truncated = $granularity === 'hour' ? 'hour' : 'day';
            $selects[] = "date_trunc('" . $truncated . "', {$tzShift}) AS bucket";
            $group[] = 'bucket';
        }

        if ($byKey) {
            $selects[] = $byAction ? 'action AS key' : $this->entityCaseExpression().' AS key';
            $group[] = 'key';
        }

        $selects[] = 'COUNT(*) AS count';

        $rows = $query
            ->selectRaw(implode(', ', $selects))
            ->groupBy($group)
            ->get()
            ->map(function ($row) use ($granularity, $byDay, $byKey) {
                $bucket = null;
                if ($byDay && $row->bucket !== null) {
                    $bucket = $granularity === 'hour'
                        ? substr((string) $row->bucket, 0, 13).':00'
                        : substr((string) $row->bucket, 0, 10);
                }
                // При group_by=day «ключ» не нужен — это единый ряд активности.
                $key = $byKey ? $row->key : null;

                return [
                    'bucket' => $bucket,
                    'key'    => $key,
                    'count'  => (int) $row->count,
                ];
            });

        // Хронология — по датам; итоги по действиям/объектам — по убыванию.
        $rows = $byDay
            ? $rows->sortBy('bucket')
            : $rows->sortByDesc('count');

        return response()->json(['data' => $rows->values()]);
    }

    /**
     * Расход по расходуемым свойствам за период.
     *
     * Журнал показывает каждое движение отдельной строкой, а человек спросит
     * «сколько масла списали за неделю» — и не найдёт: суммы по свойствам нигде
     * нет, а сложить две сотни строк в уме не выйдет.
     *
     * Текущий остаток сюда не входит: он и так есть в карточке, и считать его
     * здесь — значит держать второе место, где он живёт.
     *
     * @return array<int, array{property_id: int, property_title: string|null, unit_short: string|null, writeoff: float, replenish: float}>
     */
    public function partialSummary(Request $request): JsonResponse
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
            ->whereIn('action', ['operation.replenish', 'operation.writeoff'])
            ->whereNotNull('payload->property_id')
            ->orderBy('id')
            ->get(['action', 'payload']);

        $totals = [];
        $titles = [];

        foreach ($rows as $row) {
            $payload = $row->payload ?? [];
            $propertyId = (int) ($payload['property_id'] ?? 0);

            if ($propertyId <= 0) {
                continue;
            }

            $amount = (float) ($payload['amount'] ?? 0);
            // action — enum, и сравнение со строкой всегда было ложным: любая
            // сумма попадала в пополнение, а списание выглядело как приход.
            $key = $row->action === AuditAction::OperationWriteoff ? 'writeoff' : 'replenish';

            $totals[$propertyId][$key] = ($totals[$propertyId][$key] ?? 0.0) + $amount;
            $titles[$propertyId] ??= $payload['property_title'] ?? null;
        }

        $units = Property::query()
            ->with('unit')
            ->whereIn('id', array_keys($totals))
            ->get()
            ->keyBy('id');

        $summary = [];

        foreach ($totals as $propertyId => $sums) {
            $summary[] = [
                'property_id'    => (int) $propertyId,
                'property_title' => $units[$propertyId]->title ?? $titles[$propertyId] ?? null,
                'unit_short'     => $units[$propertyId]->unit?->title_short,
                'unit_full'      => $units[$propertyId]->unit?->title_full,
                'writeoff'       => $sums['writeoff'] ?? 0.0,
                'replenish'      => $sums['replenish'] ?? 0.0,
            ];
        }

        return response()->json(['data' => $summary]);
    }

    /**
     * Ряд остатков во времени для предмета.
     *
     * Берётся из строк операций, а не из журнала действий. Журнал обрезается
     * на 2000 записей и не хранит остатков вовсе — при длинной истории ряд
     * начинался с середины, а стартовой точки не было никогда: при создании
     * карточки в журнал падает снапшот, а остатков по свойствам там нет, потому
     * что на тот момент их никто не считал.
     *
     * В строке операции лежит и то и другое: сколько штук было и стало
     * (before/after) и сколько осталось по каждому расходуемому свойству
     * (property_before/property_after). Движения предмета, отменённые сторно,
     * приходят такими же строками с обратным значением — график показывает
     * откат сам, без отдельной обработки.
     *
     * Повторы отбрасываются: списание по свойствам не двигает количество, и
     * без этого в ряд по штукам попадала бы точка, равная предыдущей.
     */
    public function balance(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'entity_type' => ['required', 'string', 'in:' . implode(',', self::ENTITY_TYPES)],
            'entity_id'   => ['required', 'integer', 'min:1'],
            'date_from'   => ['sometimes', 'date', 'before_or_equal:date_to'],
            'date_to'     => ['sometimes', 'date', 'after_or_equal:date_from'],
        ])->validated();

        // Остатки по операциям есть только у предметов: склад и хранилище
        // считаются штуками, и для них ряд строится по той же таблице строк.
        if ($validated['entity_type'] !== 'item') {
            return response()->json(['data' => [], 'series' => []]);
        }

        $query = DB::table('stock_operation_item as row')
            ->join('stock_operation', 'stock_operation.id', '=', 'row.operation_id')
            ->where('row.item_id', (int) $validated['entity_id'])
            ->whereNull('stock_operation.reversed_at')
            ->orderBy('stock_operation.created_at')
            ->orderBy('stock_operation.id')
            ->orderBy('row.id');

        if (isset($validated['date_from'])) {
            $query->where('stock_operation.created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->where('stock_operation.created_at', '<=', $validated['date_to']);
        }

        $rows = $query->get([
            'stock_operation.id as operation_id',
            'stock_operation.created_at as at',
            'row.before as qty_before',
            'row.after as qty_after',
            'row.quantity',
            'row.property_id',
            'row.property_title',
            'row.property_before',
            'row.property_after',
        ]);

        $points = [];
        $lastQty = null;
        $series = [];
        $lastByProperty = [];
        $titles = [];

        foreach ($rows->groupBy('operation_id') as $operationRows) {
            $at = (string) $operationRows->first()->at;

            /*
             * Количество берётся по операции, а не по строке: одна операция по
             * предмету пишет столько строк, сколько у него расходуемых
             * свойств, и количество меняется один раз. По строкам ряд
             * «скакал» внутри одной операции — то самое, чего на графике быть не
             * должно. Штуки списаны в строке, где quantity != 0; если предмет
             * расходуется частями и штуки не двигались, берётся последняя
             * строка операции.
             */
            $unitRow = $operationRows->firstWhere('quantity', '!=', 0) ?? $operationRows->last();
            $qtyBefore = $unitRow->qty_before === null ? null : (int) $unitRow->qty_before;
            $qtyAfter = $unitRow->qty_after === null ? null : (int) $unitRow->qty_after;

            if ($qtyAfter !== null && $qtyAfter !== $lastQty && $qtyAfter !== $qtyBefore) {
                $lastQty = $qtyAfter;
                $points[] = ['at' => $at, 'qty' => $qtyAfter];
            }

            foreach ($operationRows as $row) {
                if ($row->property_id === null || $row->property_after === null) {
                    continue;
                }

                $propertyId = (int) $row->property_id;
                $after = (float) $row->property_after;

                // Повтор подряд идущих одинаковых значений: половина строк у
                // расходуемого предмета — это другие свойства, и без проверки
                // кривая дрожала бы туда-сюда без всякой причины.
                if (($lastByProperty[$propertyId] ?? null) === $after) {
                    continue;
                }

                $lastByProperty[$propertyId] = $after;
                $titles[$propertyId] ??= $row->property_title;
                $series[$propertyId]['points'][] = ['at' => $at, 'qty' => $after];
            }
        }

        $propertySeries = [];

        foreach ($series as $propertyId => $row) {
            $propertySeries[] = [
                'property_id' => $propertyId,
                'title'       => $titles[$propertyId] ?? (string) $propertyId,
                'points'      => $row['points'],
            ];
        }

        return response()->json([
            'data'   => $points,
            'series' => $propertySeries,
        ]);
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
            'category'    => 'category_id',
            'property'    => 'property_id',
            'property_group' => 'property_group_id',
            'dictionary'  => 'dictionary_id',
            'dictionary_value' => 'dictionary_value_id',
            'unit'        => 'unit_id',
            'vendor' => 'vendor_id',
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
            . "WHEN category_id IS NOT NULL THEN 'category' "
            . "WHEN property_id IS NOT NULL THEN 'property' "
            . "WHEN property_group_id IS NOT NULL THEN 'property_group' "
            . "WHEN dictionary_id IS NOT NULL THEN 'dictionary' "
            . "WHEN dictionary_value_id IS NOT NULL THEN 'dictionary_value' "
            . "WHEN unit_id IS NOT NULL THEN 'unit' "
            . "WHEN vendor_id IS NOT NULL THEN 'vendor' "
            . "ELSE 'none' END)";
    }
}