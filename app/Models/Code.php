<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    use AccessibleByUser;

    protected $table = 'code';

    protected $fillable = [
        'code',
        'store_id',
        'item_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    protected static function applyAccessibilityScope(Builder $builder, int $userId): void
    {
        $builder->where('code.user_id', $userId)
            ->orWhere(function (Builder $q) use ($userId) {
                $q->whereNotNull('code.store_id')
                    ->whereIn('code.store_id', static::accessibleStores($userId));
            })
            ->orWhere(function (Builder $q) use ($userId) {
                $q->whereNotNull('code.item_id')
                    ->whereIn('code.item_id', static::accessibleItems($userId));
            });
    }
}