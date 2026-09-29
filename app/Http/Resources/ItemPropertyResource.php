<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemPropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'item_id'             => $this->item_id,
            'property_id'         => $this->property_id,
            'property'            => $this->whenLoaded('property', fn () => $this->property === null ? null : [
                'id'         => $this->property->id,
                'title'      => $this->property->title,
                'type'       => $this->property->type->value,
                'type_label' => $this->property->type->label(),
                'unit'       => $this->property->unit === null ? null : [
                    'id'          => $this->property->unit->id,
                    'title_short' => $this->property->unit->title_short,
                    'title_full'  => $this->property->unit->title_full,
                ],
            ]),
            // value отдаётся в виде, пригодном для типа свойства: колонки
            // value_int, value_float, value_bool и value_text считает сама
            // база, и читается та, что соответствует типу. Поэтому клиенту
            // не нужно знать про разбор значения, а смена типа у свойства
            // меняет только то, какая колонка читается.
            'value'               => $this->typedValue(),
            'dictionary_value_id' => $this->dictionary_value_id,
            'dictionary_value'    => $this->whenLoaded('dictionaryValue', fn () => $this->dictionaryValue === null ? null : [
                'id'    => $this->dictionaryValue->id,
                'title' => $this->dictionaryValue->title,
            ]),
            'sort'                => $this->sort,
        ];
    }
}
