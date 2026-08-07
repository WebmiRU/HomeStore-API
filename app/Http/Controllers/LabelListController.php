<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLabelListRequest;
use App\Http\Requests\UpdateLabelListRequest;
use App\Http\Resources\LabelListResource;
use App\Models\LabelList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class LabelListController extends Controller
{
    public function index(): ResourceCollection
    {
        return LabelListResource::collection(
            LabelList::with(['labelPreset', 'items.code', 'stores.code'])
                ->orderBy('id')
                ->paginate()
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
}
