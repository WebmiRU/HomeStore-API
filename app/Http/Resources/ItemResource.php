<?php

namespace App\Http\Resources;

use App\Http\Resources\ImageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type'    => 'item',
            'code'    => $this->whenLoaded('code', fn() => $this->code?->code),
            'payload' => [
                'id'          => $this->id,
                'user_id'     => $this->user_id,
                'user'        => $this->relationLoaded('user') && $this->user !== null
                    ? new UserBriefResource($this->user)
                    : $this->when(false, null),
                'title'       => $this->title,
                'title_print' => $this->title_print,
                'store_id'    => $this->store_id,
                'quantity'    => $this->quantity,
                'created_at'  => $this->created_at,
                'updated_at'  => $this->updated_at,
            ],
            'store'   => $this->whenLoaded('store', function () {
                $chain = [$this->store->getAttributes()];
                foreach (array_reverse($this->store->ancestors()) as $ancestor) {
                    $chain[] = $ancestor;
                }
                return $chain;
            }),
            'images'  => ImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
