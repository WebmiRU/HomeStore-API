<?php

namespace App\Http\Resources;

use App\Services\TrashService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Строка корзины.
 *
 * Отдельный ресурс, а не переиспользование ресурсов сущностей: в корзине у
 * записи нет большую часть полей, а вместо них — когда удалили. Держать
 * в общем виде строки списков значило бы отдавать пустые обязательные поля.
 */
class TrashEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            // Заголовок собирается по правилам сущности: у единицы измерения
            // их два, у прочих одно название.
            'title'        => app(TrashService::class)->titleOf($this->resource),
            'deleted_at'   => $this->deleted_at,
            'deleted_date' => $this->deleted_at?->format('d.m.Y H:i'),
        ];
    }
}
