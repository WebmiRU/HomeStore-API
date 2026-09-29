<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Остаток расходуемого свойства внутри текущей штуки.
 *
 * Целые штуки хранятся в item.quantity, а здесь — сколько ещё осталось в той
 * штуке, которая сейчас расходуется. Суммарный остаток, который видит
 * человек, получается сложением целых штук и этого остатка:
 *
 *   (quantity - 1) × норма + remaining
 *
 * Когда остаток обнулился и предмет ещё не списан, он переходит на следующую
 * штуку, и здесь снова становится нормой.
 */
class ItemPartialRemaining extends Model
{
    protected $table = 'item_partial_remaining';

    protected $fillable = [
        'item_id',
        'property_id',
        'remaining',
    ];

    protected function casts(): array
    {
        return [
            'remaining' => 'float',
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
