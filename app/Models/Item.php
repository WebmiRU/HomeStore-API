<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUser;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use Searchable;
    use AccessibleByUser;

    protected $table = 'item';

    protected $fillable = [
        'title',
        'title_print',
        'store_id',
        'category_id',
        'quantity',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function code()
    {
        return $this->hasOne(Code::class);
    }

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Сначала по свойству, потом по sort: sort у всех свойств начинается с
     * нуля, и сортировка только по нему оставляла бы порядок свойств
     * произвольным — а список значений печатается на этикетке и должен
     * быть одинаковым от загрузки к загрузке.
     */
    public function propertyValues()
    {
        return $this->hasMany(ItemProperty::class, 'item_id')
            ->orderBy('item_property.property_id')
            ->orderBy('item_property.sort');
    }

    public function images()
    {
        return $this->belongsToMany(Image::class, 'image_m2m_item')
            ->withPivot('image_id', 'item_id', 'alt', 'weight')
            ->withTimestamps()
            ->orderBy('image_m2m_item.weight');
    }

    protected static function applyAccessibilityScope(Builder $builder, int $userId): void
    {
        $builder->where('item.user_id', $userId)
            ->orWhere(function (Builder $q) use ($userId) {
                $q->whereNotNull('item.store_id')
                    ->whereIn('item.store_id', static::accessibleStores($userId));
            });
    }
}
