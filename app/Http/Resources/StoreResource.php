<?php

namespace App\Http\Resources;

use App\Http\Resources\ImageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'title'       => $this->title,
            'title_print' => $this->title_print,
            'parent_id'   => $this->parent_id,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'parents'     => array_reverse($this->ancestors()),
            'code'        => $this->whenLoaded('code', fn () => $this->code?->code),
            'images'      => ImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
