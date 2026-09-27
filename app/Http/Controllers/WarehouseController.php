<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Services\AccessService;
use App\Services\StorageContentsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WarehouseController extends Controller
{
    public function index(): ResourceCollection
    {
        return WarehouseResource::collection(
            Warehouse::with('user')
                ->orderByDesc('id')
                ->paginate()
        );
    }

    public function all(): ResourceCollection
    {
        return WarehouseResource::collection(
            Warehouse::with('user')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function get(Warehouse $model): WarehouseResource
    {
        return new WarehouseResource($model->load('user'));
    }

    /**
     * Что лежит на складе: дерево хранилищ с предметами.
     *
     * Отдельный маршрут, а не поле в карточке склада: дерево — это отдельная
     * вкладка со своим объёмом данных, и тянуть его при каждом открытии
     * карточки значило бы грузить его и на других вкладках, где он не виден.
     */
    public function contents(Warehouse $model, StorageContentsService $contents): JsonResponse
    {
        abort_unless(app(AccessService::class)->canEdit($model), 403, 'Недостаточно прав для просмотра склада');

        return response()->json(['data' => $contents->treeForWarehouse($model)]);
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
        abort_unless(app(AccessService::class)->canEdit($model), 403, 'Недостаточно прав для редактирования склада');

        $model->update($request->validated());

        return new WarehouseResource($model->load('user'));
    }

    public function delete(Warehouse $model): JsonResponse
    {
        abort_unless(app(AccessService::class)->canDelete($model), 403, 'Недостаточно прав для удаления склада');

        $model->delete();

        return response()->json(null, 204);
    }
}