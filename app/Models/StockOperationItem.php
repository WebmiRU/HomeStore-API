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
        'released_code_id',
        'property_id',
        'amount',
        'property_before',
        'property_after',
        'reversed_amount',
    ];

    protected $attributes = [
        'reversed_quantity' => 0,
        'reversed_amount'   => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reversed_quantity' => 'integer',
            'before' => 'integer',
            'after' => 'integer',
            'amount' => 'float',
            'property_before' => 'float',
            'property_after' => 'float',
            'reversed_amount' => 'float',
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

    /** Код, высвобождённый этим списанием, если списание было по коду. */
    public function releasedCode()
    {
        return $this->belongsTo(Code::class, 'released_code_id');
    }

    /** Связь со свойством, по которому списан расход. */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Строка частичного списания: расход по свойству, а не целые штуки.
     *
     * Отличается тем, что amount задан, а quantity может быть нулём: списали
     * 300 мл из бутылки — штуки не списались, а расход по свойству был.
     */
    public function isPartial(): bool
    {
        return $this->amount !== null;
    }

    /**
     * Сколько ещё можно вернуть по этой строке.
     *
     * Возврат бывает частичным и у обычной строки («вернули 4 из 10»), и у
     * строки по свойству («вернули 100 мл из 300»), поэтому число не всегда
     * целое. Верхняя граница своя у каждого вида строк.
     */
    public function remaining(): float
    {
        if ($this->isPartial()) {
            return max(0.0, (float) $this->amount - (float) $this->reversed_amount);
        }

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
