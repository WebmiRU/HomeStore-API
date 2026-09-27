<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUser;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;
    use Searchable;
    use AccessibleByUser;

    protected $table = 'item';

    protected $fillable = [
        'title',
        'title_print',
        'store_id',
        'category_id',
        'vendor_id',
        'quantity',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * Все коды предмета в заданном порядке: верхний в списке на форме —
     * первый здесь.
     *
     * Их несколько: один штрихкод — одна физическая наклейка, а предмет
     * вещь абстрактная, и наклеек на ней может быть сколько угодно
     * (своя, этикетка из чужого набора, код со склада-источника).
     *
     * Порядок задаётся code.sort, а не id: код, заведённый позже, получает
     * больший id, но стоять выше может только по воле человека. По id
     * главным оказался бы тот, кто дольше всех пролежал в базе.
     */
    public function codes()
    {
        return $this->hasMany(Code::class)
            ->orderBy('code.sort')
            ->orderBy('code.id');
    }

    /**
     * Главный код — первый в codes, то есть верхний в списке на форме
     * предмета. По нему печатается этикетка предмета и он показывается там,
     * где код нужен один: карточка, поиск, печать этикеток по умолчанию.
     */
    public function code()
    {
        return $this->hasOne(Code::class)
            ->orderBy('code.sort')
            ->orderBy('code.id');
    }

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id')->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id')->withTrashed();
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
