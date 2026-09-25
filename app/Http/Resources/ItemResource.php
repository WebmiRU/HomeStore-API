<?php

namespace App\Http\Resources;

use App\Http\Resources\ImageResource;
use App\Models\Item;
use App\Services\AccessService;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rights = $this->resource instanceof Item
            ? app(AccessService::class)->rightsFor($this->resource)
            : [];

        return [
            'type'    => 'item',
            'rights'  => $rights,
            'is_owner'=> $this->user_id !== null && (int) $this->user_id === (int) CurrentUser::id(),
            'can_edit'=> in_array('edit', $rights, true),
            'can_delete' => in_array('delete', $rights, true),
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
