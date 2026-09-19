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
            'user'    => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'type'    => $type,
            'payload' => $type && $this->relationLoaded($type) && $this->{$type}
                ? array_merge($this->{$type}->getAttributes(), [
                    'user'   => $this->relationLoaded('user') && $this->user !== null
                        ? (new UserBriefResource($this->user))->resolve($request)
                        : null,
                    'images' => ImageResource::collection($this->{$type}->images)
                        ->resolve($request),
                ])
                : null,
        ];
    }
}
