<?php

namespace App\Http\Resources;

use App\Http\Resources\ImageResource;
use App\Models\Store;
use App\Services\AccessService;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rights = $this->resource instanceof Store
            ? app(AccessService::class)->rightsFor($this->resource)
            : [];

        return [
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'user'        => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'rights'      => $rights,
            'is_owner'    => $this->user_id !== null && (int) $this->user_id === (int) CurrentUser::id(),
            'can_create'  => in_array('create', $rights, true),
            'can_edit'    => in_array('edit', $rights, true),
            'can_delete'  => in_array('delete', $rights, true),
            'title'       => $this->title,
            'title_print' => $this->title_print,
            'parent_id'   => $this->parent_id,
            'warehouse_id'=> $this->warehouse_id,
            'warehouse'   => $this->relationLoaded('warehouse') && $this->warehouse !== null
                ? ['id' => $this->warehouse->id, 'title' => $this->warehouse->title]
                : $this->when(false, null),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'parents'     => array_reverse($this->ancestors()),
            'code'        => $this->whenLoaded('code', fn () => $this->code?->code),
            'images'      => ImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
