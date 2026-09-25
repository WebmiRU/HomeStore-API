<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlankLabelListRequest;
use App\Http\Requests\StoreLabelListRequest;
use App\Http\Requests\UpdateLabelListRequest;
use App\Http\Resources\LabelListResource;
use App\Models\Item;
use App\Models\LabelList;
use App\Models\LabelPreset;
use App\Models\Store;
use App\Services\Pdf\LabelPdfService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LabelListController extends Controller
{
    public function __construct(
        private readonly LabelPdfService $labelService,
    ) {}

    public function index(): ResourceCollection
    {
        return LabelListResource::collection(
            LabelList::with(['labelPreset.user', 'items.code', 'stores.code', 'user', 'items.user', 'stores.user'])
                ->withCount('codes')
                ->orderByDesc('id')
                ->paginate()
        );
    }

    public function all(): ResourceCollection
    {
        return LabelListResource::collection(
            LabelList::with(['labelPreset.user', 'items', 'stores', 'user', 'items.user', 'stores.user'])
                ->withCount('codes')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function get(LabelList $model): LabelListResource
    {
        return new LabelListResource(
            $model->load(['labelPreset.user', 'items.code', 'stores.code', 'user', 'items.user', 'stores.user'])
                ->loadCount('codes')
        );
    }

    public function post(StoreLabelListRequest $request): JsonResponse
    {
        $list = LabelList::create($request->validated());

        return (new LabelListResource($list->load(['labelPreset.user', 'items.code', 'stores.code', 'user', 'items.user', 'stores.user'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Создаёт набор безымянных этикеток: столько свободных кодов, сколько
     * помещается на лист по шаблону, привязанных к этому набору.
     *
     * Всё в одной транзакции — набор не должен существовать без своих кодов.
     *
     * POST /api/label-list/blank
     */
    public function blank(StoreBlankLabelListRequest $request): JsonResponse
    {
        $preset = LabelPreset::findOrFail($request->validated('label_preset_id'));
        $count = $preset->layout()['per_page'];

        $list = DB::transaction(function () use ($preset, $count): LabelList {
            // Название собирается из id, а id известен только после вставки,
            // поэтому сначала ставим заведомо уникальное временное имя.
            $list = LabelList::create([
                'title'           => 'tmp-' . Str::uuid(),
                'label_preset_id' => $preset->id,
            ]);

            $now = now();
            $userId = CurrentUser::id();
            $codes = [];

            for ($i = 0; $i < $count; $i++) {
                $codes[] = [
                    // UUIDv7 без дефисов: символ DataMatrix на два модуля
                    // меньше, чем у дефисной записи, при той же ячейке.
                    'code'          => str_replace('-', '', (string) Str::uuid7()),
                    'user_id'       => $userId,
                    'label_list_id' => $list->id,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            // Одной вставкой: codes по одной через create() — это count
            // обращений к БД вместо одного.
            foreach (array_chunk($codes, 500) as $chunk) {
                DB::table('code')->insert($chunk);
            }

            $list->update(['title' => 'Безымянные этикетки ' . $list->id]);

            return $list;
        });

        return (new LabelListResource($list->load(['labelPreset.user', 'user'])->loadCount('codes')))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateLabelListRequest $request, LabelList $model): LabelListResource
    {
        $model->update($request->validated());

        return new LabelListResource(
            $model->load(['labelPreset.user', 'items.code', 'stores.code', 'user', 'items.user', 'stores.user'])
                ->loadCount('codes')
        );
    }

    public function delete(LabelList $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }

    public function attachItem(LabelList $labelList, Item $item): JsonResponse
    {
        $labelList->items()->syncWithoutDetaching([$item->id]);

        return response()->json(['attached' => true]);
    }

    public function detachItem(LabelList $labelList, Item $item): JsonResponse
    {
        $labelList->items()->detach($item->id);

        return response()->json(['detached' => true]);
    }

    public function attachStore(LabelList $labelList, Store $store): JsonResponse
    {
        $labelList->stores()->syncWithoutDetaching([$store->id]);

        return response()->json(['attached' => true]);
    }

    public function detachStore(LabelList $labelList, Store $store): JsonResponse
    {
        $labelList->stores()->detach($store->id);

        return response()->json(['detached' => true]);
    }

    /**
     * Генерирует PDF с этикетками для всех предметов и хранилищ списка.
     *
     * GET /api/label-list/{labelList}/generate
     */
    public function generate(LabelList $labelList): Response|JsonResponse
    {
        $labelList->load(['labelPreset.font', 'items.code', 'stores.code', 'codes']);

        $labels = [];

        foreach ($labelList->items as $item) {
            if ($item->code) {
                $labels[] = [
                    'code' => $item->code->code,
                    'title' => $item->title_print ?: $item->title,
                ];
            }
        }

        foreach ($labelList->stores as $store) {
            if ($store->code) {
                $labels[] = [
                    'code' => $store->code->code,
                    'title' => $store->title_print ?: $store->title,
                ];
            }
        }

        // Собственные коды набора (безымянные этикетки). У них нет названия,
        // поэтому title пустой — шаблон с show_text=false отрисует только код.
        foreach ($labelList->codes as $code) {
            $labels[] = [
                'code'  => $code->code,
                'title' => '',
            ];
        }

        if (empty($labels)) {
            return response()->json(['error' => 'Нет кодов для генерации этикеток'], 422);
        }

        $options = $labelList->labelPreset?->toOptions() ?? [];

        $filename = 'labels-'.mb_ereg_replace('[^a-zA-Z0-9а-яА-Я_-]', '_', $labelList->title).'.pdf';
        $pdf = $this->labelService->generate($labels, $options);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
