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
                $builder->where($builder->getModel()->getTable().'.user_id', CurrentUser::id());
            }
        });
    }
}