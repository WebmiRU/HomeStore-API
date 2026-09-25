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
            'label' => 'все, независимо от возраста',
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
