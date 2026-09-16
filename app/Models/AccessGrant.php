<?php

namespace App\Models;

use App\Services\AccessService;
use Illuminate\Database\Eloquent\Model;

/**
 * Грант доступа: владелец сущности разрешает другому пользователю
 * права на конкретную сущность или на все сущности данного типа.
 *
 * Инвариант: любое другое право (create/create/edit/delete) неявно
 * включает «просмотр» (view) — видимость сущностей гейтится именно
 * правом view. Нормализация выполняется моделью при сохранении,
 * чтобы гарантировать инвариант независимо от источника данных.
 */
class AccessGrant extends Model
{
    protected $table = 'access_grant';

    protected $fillable = [
        'owner_id',
        'user_id',
        'entity_type',
        'entity_id',
        'rights',
    ];

    protected $casts = [
        'rights' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (AccessGrant $grant) {
            $grant->rights = self::normalizeRights($grant->rights ?? []);
        });
    }

    public static function normalizeRights(array $rights): array
    {
        $rights = array_values(array_unique(array_map('strval', $rights)));
        $rights = array_values(array_intersect($rights, AccessService::ALLOWED));

        if (! in_array('view', $rights, true) && count(array_intersect(['create', 'edit', 'delete'], $rights)) > 0) {
            $rights[] = 'view';
        }

        return $rights;
    }

    public function owner()
    {
        return $this->belongsTo(UserProfile::class, 'owner_id');
    }

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'entity_id');
    }
}