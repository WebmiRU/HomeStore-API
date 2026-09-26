<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyGroupRequest;
use App\Http\Requests\UpdatePropertyGroupRequest;
use App\Http\Resources\PropertyGroupResource;
use App\Models\PropertyGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PropertyGroupController extends Controller
{
    public function index(): ResourceCollection
    {
        return PropertyGroupResource::collection(
            PropertyGroup::with('user')->withCount('properties')->orderBy('id')->paginate()
        );
    }

    public function all(): ResourceCollection
    {
        return PropertyGroupResource::collection(
            PropertyGroup::withCount('properties')->orderBy('id')->get()
        );
    }

    public function get(PropertyGroup $model): PropertyGroupResource
    {
        return new PropertyGroupResource($model->load('user')->loadCount('properties'));
    }

    public function post(StorePropertyGroupRequest $request): JsonResponse
    {
        $group = PropertyGroup::create($request->validated());

        return (new PropertyGroupResource($group->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdatePropertyGroupRequest $request, PropertyGroup $model): PropertyGroupResource
    {
        // group_id в property объявлен nullOnDelete: удаление группы оставляет
        // её свойства на месте, просто без группы. Сами свойства при этом
        // не трогаются, обходить их не нужно.
        $model->update($request->validated());

        return new PropertyGroupResource($model->load('user'));
    }

    public function delete(PropertyGroup $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }
}
