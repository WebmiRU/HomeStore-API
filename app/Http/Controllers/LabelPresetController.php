<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLabelPresetRequest;
use App\Http\Requests\UpdateLabelPresetRequest;
use App\Http\Resources\LabelPresetResource;
use App\Models\LabelList;
use App\Models\LabelPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class LabelPresetController extends Controller
{
    public function index(): ResourceCollection
    {
        return LabelPresetResource::collection(
            LabelPreset::with(['font', 'user'])
                ->orderByDesc('id')
                ->paginate()
        );
    }

    public function get(LabelPreset $model): LabelPresetResource
    {
        return new LabelPresetResource($model->load(['font', 'user']));
    }

    public function post(StoreLabelPresetRequest $request): JsonResponse
    {
        $preset = LabelPreset::create($request->validated());

        return (new LabelPresetResource($preset->load(['font', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateLabelPresetRequest $request, LabelPreset $model): LabelPresetResource
    {
        $model->update($request->validated());

        return new LabelPresetResource($model->load(['font', 'user']));
    }

    public function delete(LabelPreset $model): JsonResponse
    {
        // Удаляем дочерние списки через Eloquent, чтобы обзерверы записали
        // label_list.deleted в журнал (каскад на уровне БД их бы пропустил).
        DB::transaction(function () use ($model): void {
            foreach ($model->labelLists as $labelList) {
                $labelList->delete();
            }

            $model->delete();
        });

        return response()->json(null, 204);
    }
}
