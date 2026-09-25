<?php

namespace App\Models;

use App\Models\Concerns\AccessibleByUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    use AccessibleByUser;

    protected $table = 'code';

    protected $fillable = [
        'code',
        'store_id',
        'item_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Набор этикеток, в котором код был напечатан. Не NULL — только у кодов
     * из сгенерированных наборов; обычные коды предметов и хранилищ
     * к наборам не относятся.
     */
    public function labelList()
    {
        return $this->belongsTo(LabelList::class);
    }

    protected static function applyAccessibilityScope(Builder $builder, int $userId): void
    {
        $builder->where('code.user_id', $userId)
            ->orWhere(function (Builder $q) use ($userId) {
                $q->whereNotNull('code.store_id')
                    ->whereIn('code.store_id', static::accessibleStores($userId));
            })
            ->orWhere(function (Builder $q) use ($userId) {
                $q->whereNotNull('code.item_id')
                    ->whereIn('code.item_id', static::accessibleItems($userId));
            });
    }

    /**
     * Детерминированный порядок разрешения кода: сначала свои, затем чужие,
     * внутри групп — по убыванию id (новые выше). Используется при коллизии
     * одинаковых кодов у нескольких предметов.
     */
    public function scopeMatchesFor(Builder $builder, int $userId): void
    {
        $builder->orderByRaw('(code.user_id = ?) DESC', [$userId])
            ->orderByDesc('code.id');
    }

    /**
     * Осиротевшие коды: не привязаны ни к предмету, ни к хранилищу,
     * ни к набору этикеток.
     *
     * Условие по label_list_id здесь не косметика. Свободный код, который
     * принадлежит живому набору, — это рабочая безымянная наклейка: она
     * наклеена и ждёт привязки, удалять её нельзя. Осиротевшим код
     * становится только тогда, когда набор удалён, а набор удаляют
     * намеренно, оставляя коды на наклейках.
     *
     * Глобальный scope AccessibleByUser добавляет свои условия скобкой,
     * поэтому AND-условия этого scope не разъезжаются с его OR-цепочкой.
     */
    public function scopeOrphaned(Builder $builder): void
    {
        $builder->whereNull('code.item_id')
            ->whereNull('code.store_id')
            ->whereNull('code.label_list_id');
    }

    /**
     * Код старше указанного числа дней. 0 — без ограничения по возрасту.
     */
    public function scopeOlderThan(Builder $builder, int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $builder->where('code.created_at', '<', now()->subDays($days));
    }
}
