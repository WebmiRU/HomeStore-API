<?php

namespace App\Models\Concerns;

use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * «Сущность принадлежит пользователю» + прозрачная выдача прав через гранты.
 *
 * - При создании проставляет user_id текущему пользователю;
 * - накладывает глобальный scope: юзер видит свои сущности либо сущности,
 *   доступ к которым выдан грантом (каскад: Склад -> Хранилище -> Предметы,
 *   их изображения и коды).
 *
 * Корень каскада — Warehouse (entity_type='warehouse'). Модели, для которых
 * каскад не определён, по умолчанию видят только свои записи.
 */
trait AccessibleByUser
{
    public static function bootAccessibleByUser(): void
    {
        static::creating(function ($model) {
            if ($model->user_id === null && CurrentUser::id() !== null) {
                $model->user_id = CurrentUser::id();
            }
        });

        static::addGlobalScope('accessibleByUser', function (Builder $builder) {
            if (CurrentUser::id() === null) {
                return;
            }

            static::applyAccessibilityScope($builder, CurrentUser::id());
        });
    }

    /**
     * По умолчанию — только свои записи. Каскадные модели переопределяют.
     */
    protected static function applyAccessibilityScope(Builder $builder, int $userId): void
    {
        $builder->where($builder->getModel()->getTable().'.user_id', $userId);
    }

    /**
     * Подзапрос «владельцы складов, чей грант view распространяется на
     * конкретный склад текущего запроса» (конкретный или все склады владельца).
     */
    protected static function warehouseGrantOwners(int $userId): \Closure
    {
        return function ($q) use ($userId) {
            $q->select('owner_id')
                ->from('access_grant')
                ->where('user_id', $userId)
                ->where('entity_type', 'warehouse')
                ->whereRaw("rights @> '[\"view\"]'")
                ->where(function ($q) {
                    $q->whereNull('entity_id')
                        ->orWhereRaw('entity_id = warehouse.id');
                });
        };
    }

    /**
     * Подзапрос «склады, доступные пользователю»: свои либо с грантом view.
     */
    protected static function accessibleWarehouses(int $userId): \Closure
    {
        return function ($q) use ($userId) {
            $q->select('warehouse.id')
                ->from('warehouse')
                ->where(function ($q) use ($userId) {
                    $q->where('warehouse.user_id', $userId)
                        ->orWhereIn('warehouse.user_id', static::warehouseGrantOwners($userId));
                });
        };
    }

    /**
     * Подзапрос «хранилища, доступные пользователю»: свои либо привязанные
     * к доступному складу.
     */
    protected static function accessibleStores(int $userId): \Closure
    {
        return function ($q) use ($userId) {
            $q->select('store.id')
                ->from('store')
                ->where(function ($q) use ($userId) {
                    $q->where('store.user_id', $userId)
                        ->orWhere(function ($q) use ($userId) {
                            $q->whereNotNull('store.warehouse_id')
                                ->whereIn('store.warehouse_id', static::accessibleWarehouses($userId));
                        });
                });
        };
    }

    /**
     * Подзапрос «предметы, доступные пользователю»: свои либо в доступном хранилище.
     */
    protected static function accessibleItems(int $userId): \Closure
    {
        return function ($q) use ($userId) {
            $q->select('item.id')
                ->from('item')
                ->where(function ($q) use ($userId) {
                    $q->where('item.user_id', $userId)
                        ->orWhere(function ($q) use ($userId) {
                            $q->whereNotNull('item.store_id')
                                ->whereIn('item.store_id', static::accessibleStores($userId));
                        });
                });
        };
    }
}