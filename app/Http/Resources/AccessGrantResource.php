<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessGrantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id'   => $this->entity_id,
            'warehouse'   => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id'    => $this->warehouse->id,
                'title' => $this->warehouse->title,
            ] : null),
            'user'       => $this->whenLoaded('user', fn () => UserBriefResource::make($this->user)),
            'rights'     => $this->rights ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}