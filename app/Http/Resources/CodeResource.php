<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->store_id ? 'store' : ($this->item_id ? 'item' : null);

        $parents = [];

        if ($type === 'store' && $this->relationLoaded('store') && $this->store) {
            $parents = $this->store->ancestors();
        } elseif ($type === 'item' && $this->relationLoaded('item') && $this->item) {
            $item = $this->item;
            if ($item->relationLoaded('store') && $item->store) {
                $parents = array_merge(
                    $item->store->ancestors(),
                    [$item->store->getAttributes()]
                );
            }
        }

        return [
            'code' => $this->code,
            'type' => $type,
            'payload' => $type && $this->relationLoaded($type) && $this->{$type}
                ? $this->{$type}->getAttributes()
                : null,
            'parents' => $parents,
        ];
    }
}
