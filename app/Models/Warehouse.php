<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use AccessibleByUser;

    protected $table = 'warehouse';

    protected $fillable = [
        'title',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'warehouse_id');
    }

    protected static function applyAccessibilityScope(Builder $builder, int $userId): void
    {
        $builder->where('warehouse.user_id', $userId)
            ->orWhereIn('warehouse.user_id', static::warehouseGrantOwners($userId));
    }
}