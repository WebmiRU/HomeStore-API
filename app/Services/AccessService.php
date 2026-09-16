<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\Code;
use App\Models\Image;
use App\Models\Item;
use App\Models\Store;
use App\Models\UserProfile;
use App\Models\Warehouse;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Вычисление прав текущего пользователя на сущность.
 *
 * Владелец имеет полные права. Остальные — по грантам на склад (warehouse),
 * каскадом: Склад -> Хранилище -> Предметы, их изображения и коды.
 */
class AccessService
{
    public const ALLOWED = ['view', 'edit', 'delete'];

    public function canView(Model $model): bool
    {
        return in_array('view', $this->rightsFor($model), true);
    }

    public function canEdit(Model $model): bool
    {
        return in_array('edit', $this->rightsFor($model), true);
    }

    public function canDelete(Model $model): bool
    {
        return in_array('delete', $this->rightsFor($model), true);
    }

    public function rightsFor(Model $model): array
    {
        $user = CurrentUser::get();

        if ($user === null) {
            return [];
        }

        $own = $model->getAttribute('user_id');

        if ((int) $own === (int) $user->id) {
            return ['view', 'edit', 'delete'];
        }

        $warehouses = $this->governingWarehouses($model);

        if ($warehouses === []) {
            return [];
        }

        $ids = array_map(fn (Warehouse $w) => $w->id, $warehouses);
        $owners = array_values(array_unique(array_map(fn (Warehouse $w) => $w->user_id, $warehouses)));

        $grants = AccessGrant::query()
            ->where('user_id', $user->id)
            ->where('entity_type', 'warehouse')
            ->where(function ($q) use ($ids, $owners) {
                $q->whereNull('entity_id')->whereIn('owner_id', $owners);
                $q->orWhereIn('entity_id', $ids);
            })
            ->get();

        $rights = [];

        foreach ($grants as $grant) {
            $rights = array_merge($rights, $grant->rights ?? []);
        }

        return array_values(array_unique($rights));
    }

    /**
     * Склады, «управляющие» доступом к сущности (корень каскада).
     *
     * @return Warehouse[]
     */
    private function governingWarehouses(Model $model): array
    {
        if ($model instanceof Warehouse) {
            return [$model];
        }

        if ($model instanceof Store) {
            return $model->warehouse ? [$model->warehouse] : [];
        }

        if ($model instanceof Item) {
            $store = $model->store;

            return $store && $store->warehouse ? [$store->warehouse] : [];
        }

        if ($model instanceof Code) {
            $store = $model->store ?: ($model->item?->store);

            return $store && $store->warehouse ? [$store->warehouse] : [];
        }

        if ($model instanceof Image) {
            $result = [];

            foreach ($model->stores as $store) {
                if ($store->warehouse) {
                    $result[] = $store->warehouse;
                }
            }

            foreach ($model->items as $item) {
                if ($item->store && $item->store->warehouse) {
                    $result[] = $item->store->warehouse;
                }
            }

            return array_values(array_unique($result));
        }

        return [];
    }
}