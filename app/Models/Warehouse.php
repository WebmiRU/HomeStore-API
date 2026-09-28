<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model implements ImageOwner
{
    use SoftDeletes;
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

    /**
     * Фотографии склада — снимок с парковки, по нему склад и узнают.
     *
     * Порядок по весу, как у предметов и хранилищ: он же показывается первым
     * в списке и в карточке, и вес назначается при загрузке.
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'image_m2m_warehouse')
            ->withPivot('image_id', 'warehouse_id', 'alt', 'weight')
            ->withTimestamps()
            ->orderBy('image_m2m_warehouse.weight');
    }

    protected static function applyAccessibilityScope(Builder $builder, int $userId): void
    {
        $builder->where('warehouse.user_id', $userId)
            ->orWhereIn('warehouse.user_id', static::warehouseGrantOwners($userId));
    }
}