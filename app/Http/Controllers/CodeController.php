<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrphanedCodesRequest;
use App\Http\Resources\CodeResource;
use App\Models\Code;
use App\Models\LabelPreset;
use App\Services\Pdf\LabelPdfService;
use App\Support\CodeFormat;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class CodeController extends Controller
{
    /**
     * Возрастные пороги, по которым страница чистки предлагает выбрать
     * удаляемое. Считаются в днях, отсчёт от created_at кода.
     */
    private const AGE_BUCKETS = [7, 30, 90, 365];

    /**
     * Больше этого числа этикеток в предпросмотр не рисуем: PDF с десятками
     * тысяч ячеек съест память, а разглядывать их всё равно нельзя.
     */
    private const PREVIEW_LIMIT = 2000;

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
            // Коллизия: одинаковый код у нескольких доступных предметов.
            // Порядок задаёт orderCollision, а не сортировка выборки: список
            // предметов собирается в PHP, и «свои сверху» по нему уже не
            // выразить запросом.
            return response()->json([
                'code'      => trim((string) $request->query('q')),
                'ambiguous' => true,
                'matches'   => CodeResource::collection(
                    $this->orderCollision($items, (int) CurrentUser::id())
                )->resolve($request),
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

    /**
     * Список коллизий: коды, по которым предметов больше одного.
     *
     * Страница нужна, чтобы найти и починить дубли, не сканируя каждый код
     * вручную: по одному предмету понять, что он делит код с другим, нельзя.
     *
     * Отдаём постранично по значениям кода, а не по строкам code: иначе один
     * код с пятью предметами занял бы пять страниц, и в списке он бы
     * повторялся.
     *
     * GET /api/code/conflicts
     */
    public function conflicts(Request $request): JsonResponse
    {
        $userId = (int) CurrentUser::id();
        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $page = max($request->integer('page', 1), 1);

        $conflicting = Code::query()
            ->accessibleTo($userId)
            ->whereNotNull('code.item_id')
            ->select('code')
            ->groupBy('code')
            // Два одинаковых кода у одного предмета невозможны: повтор
            // внутри одного списка отсекается при сохранении. Значит «больше
            // одного предмета» — это ровно «разные предметы».
            ->havingRaw('count(distinct code.item_id) > 1')
            ->orderBy('code');

        $total = (clone $conflicting)->count('code');

        $codes = (clone $conflicting)
            ->forPage($page, $perPage)
            ->pluck('code')
            ->all();

        $rows = Code::query()
            ->with('item')
            ->whereIn('code', $codes)
            ->whereNotNull('code.item_id')
            ->orderBy('code')
            ->orderBy('code.id')
            ->get();

        $itemsByCode = [];

        foreach ($rows as $row) {
            if ($row->item === null) {
                continue;
            }

            $itemsByCode[$row->code][] = [
                'id'    => (int) $row->item_id,
                'title' => $row->item->title,
            ];
        }

        // Собираем по $codes, а не по $itemsByCode: так в ответе не появятся
        // коды, у которых предметы отфильтровались правами, и порядок строк
        // совпадёт с пагинацией.
        $data = [];

        foreach ($codes as $code) {
            $data[] = [
                'code'  => $code,
                'items' => $itemsByCode[$code] ?? [],
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max((int) ceil($total / $perPage), 1),
            ],
        ]);
    }

    /**
     * Порядок предметов при коллизии одинакового кода.
     *
     * Главный ориентир — свои предметы: человек почти всегда списывает или
     * пополняет то, что заведено у него, а чужое в том же наборе может
     * оказаться случайно. Внутри каждой группы сначала те, кого пополняли
     * позже всех: у того предмета, который последним брали в руки, на полке
     * скорее всего и лежит нужное количество. И только если пополнений не
     * было ни у кого, решает свежесть — новые сверху.
     *
     * Порядок вычисляется здесь, а не в запросе, сознательно: подзапрос за
     * последним пополнением на каждый скан стоил бы лишнего обращения к БД,
     * а коллизия — редкий случай. Сейчас сортировка выборки работает, когда
     * предмет всего один, и не важна.
     *
     * Владелец берётся у предмета, а не у строки кода: user_id у кода
     * проставляется по текущему пользователю в момент создания, и у кодов,
     * заведённых мимо API (сидеры, консоль), он пуст — а предмет хозяин
     * всегда.
     *
     * @param  \Illuminate\Support\Collection<int, Code>  $items  предметы по id
     * @return \Illuminate\Support\Collection<int, Code>
     */
    private function orderCollision(Collection $items, int $userId): Collection
    {
        $replenishedAt = $this->lastReplenishedAt($items->keys()->all());

        // Сортировка на PHP 8 устойчива, поэтому при полном равенстве
        // сохраняется порядок выборки, а не случайный.
        return $items->sort(function (Code $a, Code $b) use ($replenishedAt, $userId): int {
            $aOwn = (int) $a->item?->user_id === $userId;
            $bOwn = (int) $b->item?->user_id === $userId;

            if ($aOwn !== $bOwn) {
                return $aOwn ? -1 : 1;
            }

            $aAt = $replenishedAt[$a->item_id] ?? null;
            $bAt = $replenishedAt[$b->item_id] ?? null;

            if ($aAt !== $bAt) {
                // Без пополнений — в конец своей группы, а не в начало:
                // иначе предмет, которым ни разу не пользовались, оказался
                // бы первым просто потому, что пополнений не было вовсе.
                if ($aAt === null) {
                    return 1;
                }
                if ($bAt === null) {
                    return -1;
                }

                return $bAt <=> $aAt;
            }

            return $b->id <=> $a->id;
        })->values();
    }

    /**
     * Время последнего пополнения по каждому предмету: item_id => timestamp.
     *
     * Откатанные операции не считаются: возвращённое пополнение не было
     * пополнением, и предмет, который только что откатили, не должен
     * стоять первым как «последний, кого брали».
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, int>
     */
    private function lastReplenishedAt(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = DB::table('stock_operation_item')
            ->join('stock_operation', 'stock_operation.id', '=', 'stock_operation_item.operation_id')
            ->whereIn('stock_operation_item.item_id', $itemIds)
            ->where('stock_operation.direction', 'replenish')
            ->whereNull('stock_operation.reversed_at')
            ->groupBy('stock_operation_item.item_id')
            ->select('stock_operation_item.item_id', DB::raw('max(stock_operation.created_at) as last_replenished'))
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->item_id] = (int) strtotime((string) $row->last_replenished);
        }

        return $result;
    }

    /**
     * Сводка по осиротевшим кодам: сколько всего, когда самый старый и
     * самый новый, и сколько попадает в каждую возрастную корзину.
     *
     * Корзины отдаются сервером, а не зашиты во фронтенд: набор порогов
     * влияет на то, что страница вообще предложит удалить.
     *
     * GET /api/code/orphans
     */
    public function orphans(OrphanedCodesRequest $request): JsonResponse
    {
        $total = Code::orphaned()->count();

        $fields = [
            'count(*) as total',
            'min(code.created_at) as oldest',
            'max(code.created_at) as newest',
        ];

        foreach (self::AGE_BUCKETS as $days) {
            // count(*) filter (...) — агрегат Postgres, позволяющий посчитать
            // все корзины за один проход вместо четырёх отдельных запросов.
            // Поддержка FILTER здесь гарантирована: миграции проекта
            // Postgres-специфичные и на чём-то другом всё равно не поедут.
            $fields[] = "count(*) filter (where code.created_at < now() - interval '{$days} days') as d{$days}";
        }

        $row = Code::orphaned()
            ->selectRaw(implode(', ', $fields))
            ->first();

        $buckets = [];
        foreach (self::AGE_BUCKETS as $days) {
            $buckets[] = [
                'days'  => $days,
                'label' => 'старше ' . $days . ' дн.',
                'count' => (int) ($row->{'d' . $days} ?? 0),
            ];
        }

        // Без ограничения по возрасту — последним и с явным названием.
        // Без него страница оказывается тупиком на свежих кодах: все
        // возрастные корзины пусты, а удалить всё разом нужно.
        $buckets[] = [
            'days'  => 0,
            'label' => __('все, независимо от возраста'),
            'count' => $total,
        ];

        return response()->json([
            'total'   => $total,
            'oldest'  => $row?->oldest,
            'newest'  => $row?->newest,
            'buckets' => $buckets,
        ]);
    }

    /**
     * Предпросмотр удаляемого: те же коды, что удалит destroyOrphans с тем
     * же фильтром, отрисованные настоящими этикетками. Нужен, чтобы сверить
     * удаляемое с остатками бумаги — сами по себе коды нечитаемы.
     *
     * GET /api/code/orphans/preview?older_than_days=30
     */
    public function orphansPreview(OrphanedCodesRequest $request, LabelPdfService $labelService): Response|JsonResponse
    {
        $query = Code::orphaned()->olderThan($request->days());

        $total = (clone $query)->count();

        $codes = $query->orderBy('code.id')
            ->limit(self::PREVIEW_LIMIT)
            ->pluck('code');

        if ($codes->isEmpty()) {
            return response()->json(['error' => 'Нет кодов для предпросмотра'], 422);
        }

        $pdf = $labelService->generate(
            $codes->map(fn (string $code) => ['code' => $code, 'title' => ''])->all(),
            $this->previewOptions()
        );

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="orphaned-codes.pdf"',
            // Фронтенд по разнице предупреждает, что предпросмотр обрезан.
            'X-Codes-Total'       => (string) $total,
            'X-Codes-Rendered'    => (string) $codes->count(),
        ]);
    }

    /**
     * Массовое удаление осиротевших кодов.
     *
     * Удаляются строки, а не наклейки: отсканированный код не перестаёт
     * существовать, он просто перестаёт отличаться от любого никогда не
     * выдававшегося, и приложение начнёт отвечать на него 404. Поэтому
     * удаление всегда идёт по явному порогу возраста, а страница по
     * умолчанию подставляет самую «залежавшуюся» непустую корзину.
     *
     * DELETE /api/code/orphans?older_than_days=30
     */
    public function destroyOrphans(OrphanedCodesRequest $request): JsonResponse
    {
        $deleted = Code::orphaned()->olderThan($request->days())->delete();

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * Геометрия предпросмотра: системный шаблон, а он один на всех и
     * рассчитан ровно под безымянные этикетки. Если системного шаблона нет
     * (его ещё не отсеяли), берём любой доступный.
     */
    private function previewOptions(): array
    {
        $preset = LabelPreset::where('is_system', true)->first()
            ?? LabelPreset::orderBy('id')->first();

        return $preset?->load('font')->toOptions() ?? [];
    }
}
