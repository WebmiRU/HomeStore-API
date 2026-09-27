<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Ссылка на связанную сущность с признаком «удалена».
 *
 * Мягкое удаление оставляет ссылку в данных (item.category_id и прочие
 * не обнуляются — по решению владельца), поэтому в интерфейсе связь надо
 * как-то показать: либо названием с пометкой, либо прочерком, если подтянуть
 * удалённую сущность не удалось. Признак deleted говорит фронту, что делать:
 * название остаётся видимым, но становится некликабельным — переход к
 * удалённой записи ведёт в 404.
 *
 * Отдельный помощник, а не повтор 'deleted' => $x->trashed() в девяти
 * ресурсах: правило одно, и разъехаться оно не должно.
 */
trait MarksDeleted
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    protected function related(?Model $model, array $data): ?array
    {
        if ($model === null) {
            return null;
        }

        return $data + ['deleted' => $model->trashed()];
    }
}
