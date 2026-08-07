<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'title_print' => $this->title_print,
            'parent_id'   => $this->parent_id,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'parents'     => array_reverse($this->ancestors()),
        ];
    }
}
