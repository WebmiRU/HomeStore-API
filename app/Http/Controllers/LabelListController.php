<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLabelListRequest;
use App\Http\Requests\UpdateLabelListRequest;
use App\Http\Resources\LabelListResource;
use App\Models\Item;
use App\Models\LabelList;
use App\Models\Store;
use App\Services\Pdf\LabelPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;

class LabelListController extends Controller
{
    public function __construct(
        private readonly LabelPdfService $labelService,
    ) {}

    public function index(): ResourceCollection
    {
        return LabelListResource::collection(
            LabelList::with(['labelPreset', 'items.code', 'stores.code'])
                ->orderBy('id')
                ->paginate()
        );
    }

    public function all(): ResourceCollection
    {
        return LabelListResource::collection(
            LabelList::with(['labelPreset', 'items', 'stores'])
                ->orderBy('id')
                ->get()
        );
    }

    public function get(LabelList $model): LabelListResource
    {
        return new LabelListResource(
            $model->load(['labelPreset', 'items.code', 'stores.code'])
        );
    }

    public function post(StoreLabelListRequest $request): JsonResponse
    {
        $list = LabelList::create($request->validated());

        return (new LabelListResource($list->load(['labelPreset', 'items.code', 'stores.code'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateLabelListRequest $request, LabelList $model): LabelListResource
    {
        $model->update($request->validated());

        return new LabelListResource(
            $model->load(['labelPreset', 'items.code', 'stores.code'])
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
    public function generate(LabelList $labelList): Response
    {
        $labelList->load(['labelPreset.font', 'items.code', 'stores.code']);

        $labels = [];

        foreach ($labelList->items as $item) {
            if ($item->code) {
                $labels[] = [
                    'code'  => $item->code->code,
                    'title' => $item->title_print ?: $item->title,
                ];
            }
        }

        foreach ($labelList->stores as $store) {
            if ($store->code) {
                $labels[] = [
                    'code'  => $store->code->code,
                    'title' => $store->title_print ?: $store->title,
                ];
            }
        }

        if (empty($labels)) {
            return response()->json(['error' => 'Нет кодов для генерации этикеток'], 422);
        }

        $options = [];
        $preset = $labelList->labelPreset;

        if ($preset) {
            $options = [
                'page_width'        => $preset->page_width,
                'page_height'       => $preset->page_height,
                'page_margin_top'   => $preset->page_margin_top,
                'page_margin_right' => $preset->page_margin_right,
                'page_margin_bottom'=> $preset->page_margin_bottom,
                'page_margin_left'  => $preset->page_margin_left,
                'cell_width'        => $preset->cell_width,
                'cell_height'       => $preset->cell_height,
                'cell_pad_top'      => $preset->cell_pad_top,
                'cell_pad_right'    => $preset->cell_pad_right,
                'cell_pad_bottom'   => $preset->cell_pad_bottom,
                'cell_pad_left'     => $preset->cell_pad_left,
                'barcode_position'  => $preset->barcode_position,
                'barcode_size'      => $preset->barcode_size,
                'font_family'       => $preset->font->key ?? 'helvetica',
                'font_size_min'     => $preset->font_size_min,
                'font_size_max'     => $preset->font_size_max,
            ];
        }

        $filename = 'labels-' . mb_ereg_replace('[^a-zA-Z0-9а-яА-Я_-]', '_', $labelList->title) . '.pdf';
        $pdf = $this->labelService->generate($labels, $options);

        return response($pdf->Output('labels.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
