<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use OwnedByUser;

    protected $table = 'category';

    protected $fillable = [
        'title',
        'parent_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    /**
     * Обрезка по краям: уникальный индекс на (user_id, parent_id, title)
     * сравнивает байты, и «Болты » прошло бы проверку уникальности, чтобы
     * упереться в него на вставке.
     */
    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $value === null ? null : trim($value);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    /**
     * Идентификаторы всех потомков — по одному уровню за проход, а не рекурсией.
     *
     * Спуск в ширину, а не в глубину, упирается в php-стек на глубоком дереве,
     * а visited-защита делает обход переживающим цикл в данных: дерево,
     * склеенный неудачной правкой parent_id, иначе уронил бы и этот запрос,
     * и раскрытие дерева в интерфейсе.
     *
     * @return int[]
     */
    public function descendantIds(): array
    {
        $descendants = [];
        $visited = [$this->id => true];
        $pending = [$this->id];

        while ($pending !== []) {
            $children = static::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->all();

            $pending = [];

            foreach ($children as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $descendants[] = (int) $childId;
                $pending[] = (int) $childId;
            }
        }

        return $descendants;
    }
}
