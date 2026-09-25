<?php

namespace App\Http\Resources;

use App\Models\Warehouse;
use App\Services\AccessService;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rights = $this->resource instanceof Warehouse
            ? app(AccessService::class)->rightsFor($this->resource)
            : [];

        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'user_id'    => $this->user_id,
            'user'       => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'rights'     => $rights,
            'is_owner'   => $this->user_id !== null && (int) $this->user_id === (int) CurrentUser::id(),
            'can_create' => in_array('create', $rights, true),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}