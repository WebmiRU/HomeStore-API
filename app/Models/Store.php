<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUser;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model implements ImageOwner
{
    use SoftDeletes;
    use Searchable;
    use AccessibleByUser;

    protected $table = 'store';

    protected $fillable = [
        'title',
        'title_print',
        'parent_id',
        'warehouse_id',
        'warehouse_root_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withTrashed();
    }

    public function parent()
    {
        // С удалённым родителем связь не рвётся: в цепочке хранилищ его место
        // должно оставаться видимым, иначе цепочка обрывается на полпути.
        return $this->belongsTo(Store::class, 'parent_id')->withTrashed();
    }

    public function children()
    {
        return $this->hasMany(Store::class, 'parent_id');
    }

    /**
     * Код хранилища. Он один, но порядок задан явно: hasOne без сортировки
     * берёт произвольную строку, и при появлении лишней строки в карточке
     * молча менялся бы тот код, который человек видит.
     */
    public function code()
    {
        return $this->hasOne(Code::class)
            ->orderBy('code.sort')
            ->orderBy('code.id');
    }

    public function images(): BelongsToMany
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
                $q->whereNotNull('store.warehouse_root_id')
                    ->whereIn('store.warehouse_root_id', static::accessibleWarehouses($userId));
            });
    }

    public function rootWarehouse(): ?Warehouse
    {
        $id = $this->warehouse_root_id ?? $this->warehouse_id;

        return $id !== null ? Warehouse::find($id) : null;
    }
}
