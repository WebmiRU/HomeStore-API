<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MarksDeleted;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    use MarksDeleted;

    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'user'         => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'title'        => $this->title,
            'type'         => $this->type->value,
            // Подпись отдаём вместе со значением: интерфейсу не нужно
            // держать свой список типов в двух местах с бэкендом.
            'type_label'   => $this->type->label(),
            'group_id'     => $this->group_id,
            'group'        => $this->whenLoaded('group', fn () => $this->related($this->group, [
                'id'    => $this->group->id,
                'title' => $this->group->title,
            ])),
            'unit_id'      => $this->unit_id,
            'unit'         => $this->whenLoaded('unit', fn () => $this->related($this->unit, [
                'id'          => $this->unit->id,
                'title_short' => $this->unit->title_short,
                'title_full'  => $this->unit->title_full,
            ])),
            'dictionary_id'    => $this->dictionary_id,
            'dictionary'       => $this->whenLoaded('dictionary', fn () => $this->related($this->dictionary, [
                'id'    => $this->dictionary->id,
                'title' => $this->dictionary->title,
            ])),
            'values_count' => $this->whenCounted('values'),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
