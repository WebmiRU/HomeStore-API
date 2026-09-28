<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'url'           => $this->url(),
            'sha256'        => $this->sha256,
            'original_name' => $this->original_name,
            'mime'          => $this->mime,
            'width'         => $this->width,
            'height'        => $this->height,
            'size'          => $this->size,
            'alt'           => $this->pivot?->alt,
            'weight'        => $this->pivot?->weight,
            'created_at'    => $this->created_at,
        ];
    }
}