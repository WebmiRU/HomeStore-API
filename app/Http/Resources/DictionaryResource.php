<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DictionaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'user'         => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'title'        => $this->title,
            // Значения едут вместе со справочником: список значений нужен
            // всегда, когда справочник показывают (форма свойства, редактор
            // значения), а отдельный запрос за ними означал бы вторую
            // загрузку на каждое открытие формы.
            'values'       => DictionaryValueResource::collection($this->whenLoaded('values')),
            'values_count' => $this->whenCounted('values'),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
