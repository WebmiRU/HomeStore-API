<?php

namespace App\Http\Resources;

use App\Models\Property;
use App\Services\PartialWriteoff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodeResource extends JsonResource
{
    /**
     * Настройки расхода частями с остатками, если предмет ими расходуется.
     *
     * Считается на лету, потому что остаток зависит от количества и нормы, а
     * хранить его отдельно значило бы держать вторую копию одного и того же.
     * Пустой массив — обычный предмет, он списывается штуками.
     */
    private function partialBlock(Request $request): array
    {
        if ($this->item_id === null || ! $this->relationLoaded('item') || $this->item === null) {
            return [];
        }

        $partial = app(PartialWriteoff::class);
        $rows = [];

        foreach ($partial->settings($this->item) as $setting) {
            $norm = $partial->norm($this->item, (int) $setting->property_id);

            if ($norm === null) {
                continue;
            }

            $rows[] = [
                'property_id'     => (int) $setting->property_id,
                'property_title'  => Property::find((int) $setting->property_id)?->title,
                'step'            => (float) $setting->step,
                'is_full_reason'  => (bool) $setting->is_full_reason,
                'sort'            => (int) $setting->sort,
                'norm'            => $norm,
                'remaining'       => $partial->remaining($this->item, (int) $setting->property_id, $norm),
                'total'           => $partial->total($this->item, (int) $setting->property_id, $norm),
            ];
        }

        return $rows;
    }

    public function toArray(Request $request): array
    {
        $type = $this->store_id ? 'store' : ($this->item_id ? 'item' : null);

        return [
            'code'    => $this->code,
            'user_id' => $this->user_id,
            'user'    => $this->relationLoaded('user') && $this->user !== null
                ? new UserBriefResource($this->user)
                : $this->when(false, null),
            'type'    => $type,
            'payload' => $type && $this->relationLoaded($type) && $this->{$type}
                ? array_merge($this->{$type}->getAttributes(), [
                    'user'   => $this->relationLoaded('user') && $this->user !== null
                        ? (new UserBriefResource($this->user))->resolve($request)
                        : null,
                    'images' => ImageResource::collection($this->{$type}->images)
                        ->resolve($request),

                    // Расходуемые свойства с остатками — только у предметов.
                    // Строка сканирования строит поля расхода из них, и без
                    // них человек увидел бы обычное поле количества у предмета,
                    // который штуками не списывается.
                    'partial' => $this->partialBlock($request),
                ])
                : null,
        ];
    }
}
