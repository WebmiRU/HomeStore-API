<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'user'        => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'title'       => $this->title,
            'description' => $this->description,
            'logo_id'     => $this->logo_id,
            // Логотип отдаётся и адресом оригинала, и sha256: по нему
            // миниатюра запрашивается отдельным маршрутом, и она заметно
            // легче оригинала с логотипом во всю ширину.
            'logo_url'    => $this->logoUrl(),
            'logo_sha'    => $this->logoSha(),
            'logo_width'  => $this->logoImage?->width,
            'logo_height' => $this->logoImage?->height,
            'logo_thumbs' => $this->logoImage?->thumbLimits(),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
