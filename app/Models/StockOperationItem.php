<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Строка операции: один предмет и количество. Самостоятельного user_id нет —
 * строка достижима только через операцию, а видимость операции определяется
 * владельцами её строк (owner_id).
 */
class StockOperationItem extends Model
{
    protected $table = 'stock_operation_item';

    protected $fillable = [
        'operation_id',
        'item_id',
        'owner_id',
        'item_title',
        'source_row_id',
        'quantity',
        'reversed_quantity',
        'before',
        'after',
    ];

    protected $attributes = [
        'reversed_quantity' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reversed_quantity' => 'integer',
            'before' => 'integer',
            'after' => 'integer',
        ];
    }

    public function operation()
    {
        return $this->belongsTo(StockOperation::class, 'operation_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function owner()
    {
        return $this->belongsTo(UserProfile::class, 'owner_id');
    }

    /** Строка исходной операции, если эта строка — возврат. */
    public function sourceRow()
    {
        return $this->belongsTo(self::class, 'source_row_id');
    }

    /** Сколько ещё можно вернуть по этой строке. */
    public function remaining(): int
    {
        return max(0, $this->quantity - $this->reversed_quantity);
    }

    public function scopeVisible(Builder $query, ?int $userId = null): Builder
    {
        $userId ??= \App\Support\CurrentUser::id();

        if ($userId === null) {
            return $query;
        }

        return $query->where('stock_operation_item.owner_id', $userId);
    }
}
