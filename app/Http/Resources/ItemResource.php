<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'title_print' => $this->title_print,
            'store_id'    => $this->store_id,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'code'        => $this->whenLoaded('code', fn() => new CodeResource($this->code)),
            'store'       => $this->whenLoaded('store', fn() => $this->store->getAttributes()),
        ];
    }
}
