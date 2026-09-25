<?php

namespace App\Models\Concerns;

use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Признак «сущность принадлежит пользователю».
 *
 * - При создании автоматически проставляет user_id текущему пользователю;
 * - на все запросы накладывает глобальный scope «только свои сущности»
 *   (в консоли/миграциях, где текущего пользователя нет, scope не фильтрует).
 */
trait OwnedByUser
{
    public static function bootOwnedByUser(): void
    {
        static::creating(function ($model) {
            if ($model->user_id === null && CurrentUser::id() !== null) {
                $model->user_id = CurrentUser::id();
            }
        });

        static::addGlobalScope('ownedByUser', function (Builder $builder) {
            if (CurrentUser::id() !== null) {
                static::applyOwnershipScope($builder, CurrentUser::id());
            }
        });
    }

    /**
     * Ограничивает выборку записями текущего пользователя.
     *
     * Модель может переопределить метод, чтобы выдать дополнительные записи
     * (например, общие системные), не отказываясь от фильтрации по владельцу.
     */
    protected static function applyOwnershipScope(Builder $builder, int $userId): void
    {
        $builder->where($builder->getModel()->getTable().'.user_id', $userId);
    }
}