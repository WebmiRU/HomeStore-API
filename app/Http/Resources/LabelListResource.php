<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabelListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'user_id'         => $this->user_id,
            'user'            => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'title'           => $this->title,
            'label_preset_id' => $this->label_preset_id,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'label_preset'    => new LabelPresetResource($this->whenLoaded('labelPreset')),
            'items'           => ItemResource::collection($this->whenLoaded('items')),
            'stores'          => StoreResource::collection($this->whenLoaded('stores')),
            'codes_count'     => $this->whenCounted('codes'),
        ];
    }
}
