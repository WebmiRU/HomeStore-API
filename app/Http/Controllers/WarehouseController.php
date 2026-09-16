<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WarehouseController extends Controller
{
    public function index(): ResourceCollection
    {
        return WarehouseResource::collection(
            Warehouse::with('user')
                ->orderBy('id')
                ->paginate()
        );
    }

    public function all(): ResourceCollection
    {
        return WarehouseResource::collection(
            Warehouse::with('user')
                ->orderBy('id')
                ->get()
        );
    }

    public function get(Warehouse $model): WarehouseResource
    {
        return new WarehouseResource($model->load('user'));
    }

    public function post(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());

        return (new WarehouseResource($warehouse->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateWarehouseRequest $request, Warehouse $model): WarehouseResource
    {
        $model->update($request->validated());

        return new WarehouseResource($model->load('user'));
    }

    public function delete(Warehouse $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }
}