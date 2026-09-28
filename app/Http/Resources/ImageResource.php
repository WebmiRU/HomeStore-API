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
            /**
             * Пределы миниатюр по обрезкам: какие стороны ещё можно получить
             * из этого оригинала без увеличения. Клиент собирает srcset только
             * из них, а где размера не хватает — подставляет сам оригинал.
             */
            'thumbs'        => $this->resource->thumbLimits(),
            'alt'           => $this->pivot?->alt,
            'weight'        => $this->pivot?->weight,
            'created_at'    => $this->created_at,
        ];
    }
}