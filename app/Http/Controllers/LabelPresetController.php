<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLabelPresetRequest;
use App\Http\Requests\UpdateLabelPresetRequest;
use App\Http\Resources\LabelPresetResource;
use App\Models\LabelPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class LabelPresetController extends Controller
{
    public function index(): ResourceCollection
    {
        return LabelPresetResource::collection(
            LabelPreset::with('font')
                ->orderBy('id')
                ->paginate()
        );
    }

    public function get(LabelPreset $model): LabelPresetResource
    {
        return new LabelPresetResource($model->load('font'));
    }

    public function post(StoreLabelPresetRequest $request): JsonResponse
    {
        $preset = LabelPreset::create($request->validated());

        return (new LabelPresetResource($preset->load('font')))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateLabelPresetRequest $request, LabelPreset $model): LabelPresetResource
    {
        $model->update($request->validated());

        return new LabelPresetResource($model->load('font'));
    }

    public function delete(LabelPreset $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }
}
