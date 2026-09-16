<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Грант доступа: владелец сущности разрешает другому пользователю
 * права на конкретную сущность или на все сущности данного типа.
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