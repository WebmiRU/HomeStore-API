<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'action'     => $this->action->value,
            'entity_type' => $this->entityType(),
            'entity_id'  => $this->entityId(),
            'owner_id'   => $this->owner_id,
            'actor'      => $this->relationLoaded('actor') && $this->actor !== null
                ? new UserBriefResource($this->actor)
                : null,
            'payload'    => $this->payload,
            'created_at' => $this->created_at,
        ];
    }

    private function entityType(): ?string
    {
        if (($this->payload['entity_type'] ?? null) !== null) {
            return $this->payload['entity_type'];
        }

        foreach (['item', 'store', 'warehouse', 'label_preset', 'label_list', 'access_grant', 'user'] as $type) {
            $column = $type === 'user' ? 'target_user_id' : "{$type}_id";
            if ($this->{$column} !== null) {
                return $type;
            }
        }

        return null;
    }

    private function entityId(): ?int
    {
        if (($this->payload['entity_id'] ?? null) !== null) {
            return (int) $this->payload['entity_id'];
        }

        foreach (['item', 'store', 'warehouse', 'label_preset', 'label_list', 'access_grant', 'user'] as $type) {
            $column = $type === 'user' ? 'target_user_id' : "{$type}_id";
            if ($this->{$column} !== null) {
                return (int) $this->{$column};
            }
        }

        return null;
    }
}