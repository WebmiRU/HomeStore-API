<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Типы целей в порядке проверки. Порядок важен только для читаемости:
     * в реальной записи заполнена ровно одна колонка-цель, а если колонки
     * обнулились удалением сущности, тип и id берутся из payload.
     *
     * @var string[]
     */
    private const ENTITY_TYPES = [
        'item', 'store', 'warehouse', 'label_preset', 'label_list', 'access_grant', 'user',
        'category', 'property', 'property_group', 'dictionary', 'dictionary_value', 'unit',
    ];

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

        foreach (self::ENTITY_TYPES as $type) {
            if ($this->columnFor($type) !== null) {
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

        foreach (self::ENTITY_TYPES as $type) {
            $value = $this->columnFor($type);

            if ($value !== null) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * У пользователя колонка-цель называется не «user_id», а «target_user_id»:
     * actor_id у записи уже занят тем, кто действие совершил.
     */
    private function columnFor(string $type): mixed
    {
        return $this->{$type === 'user' ? 'target_user_id' : "{$type}_id"};
    }
}