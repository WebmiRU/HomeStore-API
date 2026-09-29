<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Настройка частичного списания: одно расходуемое свойство предмета.
 *
 * Само значение свойства лежит в item_property и означает норму на одну штуку.
 * Здесь — как эта норма расходуется: с каким шагом по умолчанию, предлагается ли
 * свойство первым и является ли его обнуление поводом списать предмет целиком.
 */
class ItemPartialProperty extends Model
{
    protected $table = 'item_partial_property';

    protected $fillable = [
        'item_id',
        'property_id',
        'step',
        'is_full_reason',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'step'            => 'float',
            'is_full_reason'  => 'boolean',
            'sort'            => 'integer',
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
}
