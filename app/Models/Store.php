<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUser;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use Searchable;
    use AccessibleByUser;

    protected $table = 'store';

    protected $fillable = [
        'title',
        'title_print',
        'parent_id',
        'warehouse_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function parent()
    {
        return $this->belongsTo(Store::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Store::class, 'parent_id');
    }

    public function code()
    {
        return $this->hasOne(Code::class);
    }

    public function images()
    {
        return $this->belongsToMany(Image::class, 'image_m2m_store')
            ->withPivot('image_id', 'store_id', 'alt', 'weight')
            ->withTimestamps()
            ->orderBy('image_m2m_store.weight');
    }

    public function ancestors(): array
    {
        $chain = [];
        $current = $this->parent;

        while ($current) {
            $chain[] = $current->getAttributes();
            $current = $current->parent;
        }

        return array_reverse($chain);
    }

    protected static function applyAccessibilityScope(Builder $builder, int $userId): void
    {
        $builder->where('store.user_id', $userId)
            ->orWhere(function (Builder $q) use ($userId) {
                $q->whereNotNull('store.warehouse_id')
                    ->whereIn('store.warehouse_id', static::accessibleWarehouses($userId));
            });
    }
}
