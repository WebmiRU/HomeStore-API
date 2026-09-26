<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'user'          => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'title'         => $this->title,
            'parent_id'     => $this->parent_id,
            'parent'        => $this->whenLoaded('parent', fn () => $this->parent === null ? null : [
                'id'    => $this->parent->id,
                'title' => $this->parent->title,
            ]),
            // Считается прямыми предметами категории. Предметы вложенных
            // категорий в счётчик не входят — иначе число у родителя всегда
            // было бы больше суммы по ветвям, и его нельзя было бы сверить.
            'items_count'   => $this->whenCounted('items'),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
