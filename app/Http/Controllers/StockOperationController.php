<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReverseStockOperationRequest;
use App\Http\Resources\StockOperationResource;
use App\Models\StockOperation;
use App\Services\StockOperationService;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockOperationController extends Controller
{
    public function __construct(private readonly StockOperationService $operations)
    {
    }

    /**
     * Журнал списаний и пополнений: операции, где участвует хотя бы один мой
     * предмет. Фильтры повторяют привычные из «Журнала действий» (период,
     * направление, поиск по комментарию), чтобы пользователю не пришлось
     * осваивать вторую манеру.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(200, max(10, (int) $request->integer('per_page', 50)));

        $query = StockOperation::query()
            ->with(['author', 'rows'])
            ->visible()
            ->orderByDesc('stock_operation.created_at')
            ->orderByDesc('stock_operation.id');

        $this->applyFilters($query, $request);

        return StockOperationResource::collection($query->paginate($perPage)->withQueryString());
    }

    /** Одна операция со строками — источник формы отката. */
    public function get(Request $request, int $model): StockOperationResource
    {
        $operation = StockOperation::query()
            ->with(['author', 'rows'])
            ->visible()
            ->findOrFail($model);

        return new StockOperationResource($operation);
    }

    public function reverse(ReverseStockOperationRequest $request, int $model): StockOperationResource
    {
        $operation = StockOperation::query()
            ->with('rows')
            ->visible()
            ->findOrFail($model);

        $reversal = $this->operations->reverse(
            $operation,
            $request->validated()['rows'],
            $request->validated()['comment'] ?? null,
        );

        return new StockOperationResource($reversal->load(['author', 'rows']));
    }

    /**
     * Сводка за те же фильтры, что и список: сколько операций и сколько штук
     * списано, пополнено, откачено. Без неё журнал — просто строки, а вопрос
     * «сколько ушло за неделю» приходилось считать на глаз.
     */
    public function summary(Request $request): JsonResponse
    {
        $totals = function (Builder $query, string $direction): array {
            $row = (clone $query)
                ->where('stock_operation.direction', $direction)
                // join, а не sum по подгруженным строкам: сводка считается
                // по всей выборке, а не по текущей странице.
                ->join('stock_operation_item', 'stock_operation_item.operation_id', '=', 'stock_operation.id')
                ->selectRaw('COUNT(DISTINCT stock_operation.id) as operations, COALESCE(SUM(stock_operation_item.quantity), 0) as units')
                ->first();

            return [
                'operations' => (int) ($row->operations ?? 0),
                'units'      => (int) ($row->units ?? 0),
            ];
        };

        $query = StockOperation::query()->visible();
        $this->applyFilters($query, $request);

        // Готовые к применению фильтры из whereHas переносятся в join
        // как есть, поэтому сводка считает то же, что показывает список.
        return response()->json([
            'writeoff'  => $totals($query, 'writeoff'),
            'replenish' => $totals($query, 'replenish'),
            'reversed'  => (clone $query)->whereNotNull('stock_operation.reversed_at')->count(),
            'reversals' => (clone $query)->whereNotNull('stock_operation.reversed_operation_id')->count(),
        ]);
    }

    /**
     * История движений по предмету — для карточки. Отдельный список, а не
     * фильтр журнала: сюда нужен предмет, а там «всё подряд».
     */
    public function itemHistory(Request $request, int $item): AnonymousResourceCollection
    {
        $perPage = min(200, max(10, (int) $request->integer('per_page', 50)));

        $query = StockOperation::query()
            ->with(['author', 'rows'])
            ->visible()
            ->whereHas('rows', fn ($rows) => $rows->where('item_id', $item))
            ->orderByDesc('stock_operation.created_at')
            ->orderByDesc('stock_operation.id');

        return StockOperationResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * Фильтры журнала. Вынесены отдельно, чтобы список и сводка нельзя было
     * развести: «в таблице показано 12, в шапке 9» — худший вид поломки.
     *
     * @param  Builder<StockOperation>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($direction = $request->string('direction')->trim()->value()) {
            $query->where('stock_operation.direction', $direction);
        }

        if ($comment = $request->string('comment')->trim()->value()) {
            $query->where('stock_operation.comment', 'ilike', '%' . $comment . '%');
        }

        if ($itemId = $request->integer('item_id')) {
            $query->whereHas('rows', fn (Builder $rows) => $rows->where('item_id', $itemId));
        }

        if ($period = $request->string('period')->trim()->value()) {
            [$from, $to] = self::periodRange(
                $period,
                $request->string('date_from')->trim()->value(),
                $request->string('date_to')->trim()->value()
            );

            if ($from !== null) {
                $query->where('stock_operation.created_at', '>=', $from);
            }

            if ($to !== null) {
                $query->where('stock_operation.created_at', '<=', $to);
            }
        }

        // «Возвраты» — это операции-откаты, а не фильтр по направлению:
        // откат списания сам по себе является пополнением, и по direction
        // его от пополнений не отличить.
        if ($request->boolean('only_reversals')) {
            $query->whereNotNull('stock_operation.reversed_operation_id');
        }

        if ($request->boolean('only_reversed')) {
            $query->whereNotNull('stock_operation.reversed_at');
        }

        if ($request->boolean('only_active')) {
            $query->whereNull('stock_operation.reversed_at');
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function periodRange(string $period, string $dateFrom, string $dateTo): array
    {
        $now = now();

        return match ($period) {
            'today'  => [$now->startOfDay()->toDateTimeString(), $now->endOfDay()->toDateTimeString()],
            'week'   => [$now->startOfWeek()->toDateTimeString(), $now->endOfWeek()->toDateTimeString()],
            'month'  => [$now->startOfMonth()->toDateTimeString(), $now->endOfMonth()->toDateTimeString()],
            'custom' => [
                $dateFrom !== '' ? $dateFrom . ' 00:00:00' : null,
                $dateTo !== '' ? $dateTo . ' 23:59:59' : null,
            ],
            default  => [null, null],
        };
    }
}
