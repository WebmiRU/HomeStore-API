<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Enums\PropertyType;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PropertyController extends Controller
{
    public function index(): ResourceCollection
    {
        return PropertyResource::collection(
            Property::with(['group', 'unit', 'dictionary', 'user'])
                ->withCount('values')
                ->orderBy('id')
                ->paginate()
        );
    }

    /**
     * Все свойства без пагинации — для подсказки «добавить свойство» в
     * форме предмета: обрезанный постранично список рано или поздно
     * оказывался бы без нужного свойства.
     */
    public function all(): ResourceCollection
    {
        return PropertyResource::collection(
            Property::with(['group', 'unit', 'dictionary'])->orderBy('id')->get()
        );
    }

    public function get(Property $model): PropertyResource
    {
        return new PropertyResource(
            $model->load(['group', 'unit', 'dictionary', 'user'])->loadCount('values')
        );
    }

    public function post(StorePropertyRequest $request): JsonResponse
    {
        $property = Property::create($request->validated());

        return (new PropertyResource($property->load(['group', 'unit', 'dictionary', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Смена типа у заполненного свойства ничего не пересчитывает.
     *
     * Значения у предметов лежат исходным вводом в одной колонке, а
     * приведённые по типам считают generated-колонки item_property: целое в
     * value_int, дробное в value_float, «да/нет» в value_bool, текст в
     * value_text. Тип решает только то, какая из них читается, поэтому
     * смена типа — это смена type, а значения остаются как введены.
     *
     * Единица и справочник привязаны к типу, поэтому при смене типа
     * привязка от прежнего типа снимается: она осталась бы в базе и мешала
     * бы, показывая единицу у текстового свойства.
     */
    public function put(UpdatePropertyRequest $request, Property $model): PropertyResource
    {
        $data = $request->validated();
        $typeChanged = array_key_exists('type', $data) && $data['type'] !== $model->type->value;

        if ($typeChanged) {
            $type = PropertyType::from($data['type']);

            if (! $type->usesUnit()) {
                $data['unit_id'] = null;
            }

            if ($type !== PropertyType::Dictionary) {
                $data['dictionary_id'] = null;
            }
        }

        $model->update($data);

        return new PropertyResource($model->load(['group', 'unit', 'dictionary', 'user']));
    }

    public function delete(Property $model): JsonResponse
    {
        // Значения предметов уносит каскад в БД: обзервера у них нет,
        // а заводить его ради удаляемых строк незачем.
        $model->delete();

        return response()->json(null, 204);
    }
}
