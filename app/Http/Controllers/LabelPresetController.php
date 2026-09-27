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
        abort_if($model->is_system, 403, 'Системный шаблон нельзя изменять');

        $model->update($request->validated());

        return new LabelPresetResource($model->load(['font', 'user']));
    }

    public function delete(LabelPreset $model): JsonResponse
    {
        abort_if($model->is_system, 403, 'Системный шаблон нельзя удалить');

        // Списки этикеток шаблон переживают: удаление мягкое, и у них
        // пропадает только ссылка на шаблон (label_list.label_preset_id
        // объявлена nullOnDelete). Раньше здесь был цикл удаления списков,
        // и стирать его нельзя было бездумно — этикетки в обороте, человек
        // назначит им другой шаблон.
        //
        // Потомки в дереве (label_list) удаляются только жёстко, из корзины.
        $model->delete();

        return response()->json(null, 204);
    }
}
