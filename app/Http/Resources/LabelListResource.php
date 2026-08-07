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
            'title'           => $this->title,
            'label_preset_id' => $this->label_preset_id,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'label_preset'    => new LabelPresetResource($this->whenLoaded('labelPreset')),
            'items'           => ItemResource::collection($this->whenLoaded('items')),
            'stores'          => StoreResource::collection($this->whenLoaded('stores')),
        ];
    }
}
