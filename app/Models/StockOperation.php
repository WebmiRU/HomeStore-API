<?php

namespace App\Models;

use App\Enums\StockDirection;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Списание или пополнение склада — одна пользовательская транзакция.
 *
 * Своего user_id-скоупа нет: операция видна по владельцам её предметов
 * (строкам), а не по автору. Списать со склада, где доступ выдан другому
 * человеку, может и его сотрудник — значит автор не определяет видимость.
 */
class StockOperation extends Model
{
    protected $table = 'stock_operation';

    protected $fillable = [
        'user_id',
        'direction',
        'comment',
        'reversed_operation_id',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => StockDirection::class,
            'reversed_at' => 'datetime',
        ];
    }

    public function author()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function rows()
    {
        return $this->hasMany(StockOperationItem::class, 'operation_id');
    }

    /** Операция, которую эта отменяет. */
    public function reversedOperation()
    {
        return $this->belongsTo(self::class, 'reversed_operation_id');
    }

    /** Откаты, созданные против этой операции (полные и частичные). */
    public function reversals()
    {
        return $this->hasMany(self::class, 'reversed_operation_id');
    }

    public function isReversal(): bool
    {
        return $this->reversed_operation_id !== null;
    }

    /** Откат зарегистрирован (полностью или частично). */
    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /**
     * Только операции, в которых участвует хотя бы один мой предмет.
     * exists вместо join: операция может состоять из строк чужих и моих
     * предметов сразу, и такую показывать нужно целиком.
     */
    public function scopeVisible(Builder $query, ?int $userId = null): Builder
    {
        $userId ??= CurrentUser::id();

        if ($userId === null) {
            return $query;
        }

        return $query->whereHas(
            'rows',
            fn (Builder $rows) => $rows->where('stock_operation_item.owner_id', $userId)
        );
    }
}
