<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UnitController extends Controller
{
    public function index(): ResourceCollection
    {
        return UnitResource::collection(
            Unit::with('user')->withCount('properties')->orderBy('id')->paginate()
        );
    }

    /**
     * Единицы без пагинации: селект в форме свойства должен показать всё,
     * иначе нужная единица оказывалась за пределами первой страницы.
     */
    public function all(): ResourceCollection
    {
        return UnitResource::collection(
            Unit::withCount('properties')->orderBy('id')->get()
        );
    }

    public function get(Unit $model): UnitResource
    {
        return new UnitResource($model->load('user')->loadCount('properties'));
    }

    public function post(StoreUnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());

        return (new UnitResource($unit->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateUnitRequest $request, Unit $model): UnitResource
    {
        // unit_id в property объявлен nullOnDelete: удаление единицы оставляет
        // свойства на месте, просто без единицы, а не сносит их вместе с собой.
        $model->update($request->validated());

        return new UnitResource($model->load('user'));
    }

    public function delete(Unit $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }
}
