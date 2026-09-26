<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Значение свойства на предмете. Своего user_id нет: строка достижима
 * только через предмет, а владение предметом уже отсечено скоупом.
 */
class ItemProperty extends Model
{
    protected $table = 'item_property';

    protected $fillable = [
        'item_id',
        'property_id',
        'value',
        'dictionary_value_id',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function dictionaryValue()
    {
        return $this->belongsTo(DictionaryValue::class, 'dictionary_value_id');
    }
}
