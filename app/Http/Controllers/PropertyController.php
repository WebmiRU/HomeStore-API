<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Enums\PropertyType;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\PropertyTypeChange;
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
     * Смена типа идёт вместе со значениями предметов: тип решает, как читать
     * текст, поэтому значения пересчитываются под новый вид, а не остаются
     * как были. Пересчитывает PropertyTypeChange, и если хоть одно значение в
     * новый тип не помещается, отказывает с объяснением, ничего не меняя.
     */
    public function put(UpdatePropertyRequest $request, Property $model, PropertyTypeChange $typeChange): PropertyResource
    {
        $data = $request->validated();

        if (array_key_exists('type', $data) && $data['type'] !== $model->type->value) {
            $typeChange->apply($model, PropertyType::from($data['type']));
        } else {
            $model->update($data);
        }

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
