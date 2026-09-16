<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccessRequest;
use App\Http\Requests\UpdateAccessRequest;
use App\Http\Resources\AccessGrantResource;
use App\Models\AccessGrant;
use App\Models\Warehouse;
use App\Support\CurrentUser;
use App\Services\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class AccessController extends Controller
{
    public function index(): ResourceCollection
    {
        return AccessGrantResource::collection(
            AccessGrant::with(['warehouse', 'user'])
                ->where('owner_id', CurrentUser::id())
                ->orderByDesc('id')
                ->get()
        );
    }

    public function forWarehouse(Warehouse $model): ResourceCollection
    {
        abort_unless((int) $model->user_id === (int) CurrentUser::id(), 403);

        return AccessGrantResource::collection(
            AccessGrant::with(['warehouse', 'user'])
                ->where('owner_id', CurrentUser::id())
                ->where('entity_type', 'warehouse')
                ->where('entity_id', $model->id)
                ->orderByDesc('id')
                ->get()
        );
    }

    public function post(StoreAccessRequest $request): JsonResponse
    {
        $user_id = (int) $request->input('user_id');

        abort_if($user_id === (int) CurrentUser::id(), 422, 'Нельзя выдать права самому себе');

        $warehouse = Warehouse::query()->withoutGlobalScopes()->findOrFail($request->input('warehouse_id'));

        abort_unless((int) $warehouse->user_id === (int) CurrentUser::id(), 403);

        $rights = $request->input('rights', []);

        $grant = DB::transaction(function () use ($warehouse, $user_id, $rights) {
            $existing = AccessGrant::query()
                ->where('owner_id', CurrentUser::id())
                ->where('entity_type', 'warehouse')
                ->where('entity_id', $warehouse->id)
                ->where('user_id', $user_id)
                ->first();

            if ($existing) {
                $existing->update(['rights' => array_values(array_unique(array_merge($existing->rights ?? [], $rights)))]);

                return $existing;
            }

            return AccessGrant::create([
                'owner_id'    => CurrentUser::id(),
                'user_id'     => $user_id,
                'entity_type' => 'warehouse',
                'entity_id'   => $warehouse->id,
                'rights'      => $rights,
            ]);
        });

        return (new AccessGrantResource($grant->load(['warehouse', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateAccessRequest $request, AccessGrant $model): AccessGrantResource
    {
        abort_unless((int) $model->owner_id === (int) CurrentUser::id(), 403);

        $model->update(['rights' => $request->validated()['rights']]);

        return new AccessGrantResource($model->load(['warehouse', 'user']));
    }

    public function delete(AccessGrant $model): JsonResponse
    {
        abort_unless((int) $model->owner_id === (int) CurrentUser::id(), 403);

        $model->delete();

        return response()->json(null, 204);
    }
}