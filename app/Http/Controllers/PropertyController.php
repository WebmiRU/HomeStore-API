<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
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

    public function put(UpdatePropertyRequest $request, Property $model): PropertyResource
    {
        $data = $request->validated();

        if (array_key_exists('type', $data) && $data['type'] !== $model->type->value) {
            // Значения лежат текстом и приведены к типу при записи. Смена типа
            // оставила бы «1,50» в свойстве, которое отныне целое, и оно
            // перестало бы попадать в нормализованное значение.
            abort_if(
                $model->values()->exists(),
                422,
                'Нельзя сменить тип: по свойству уже заполнены значения у предметов'
            );
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
