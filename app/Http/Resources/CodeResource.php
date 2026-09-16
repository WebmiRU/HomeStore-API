<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->store_id ? 'store' : ($this->item_id ? 'item' : null);

        return [
            'code'    => $this->code,
            'user_id' => $this->user_id,
            'type'    => $type,
            'payload' => $type && $this->relationLoaded($type) && $this->{$type}
                ? $this->{$type}->getAttributes()
                : null,
        ];
    }
}
