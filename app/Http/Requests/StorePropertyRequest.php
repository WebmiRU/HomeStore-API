<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = CurrentUser::id();

        return [
            'title'  => [
                'required',
                'string',
                'max:500',
                Rule::unique('property', 'title')->where('user_id', $userId),
            ],
            'type'   => ['required', Rule::enum(PropertyType::class)],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('property_group', 'id')->where('user_id', $userId),
            ],
            // Единица осмысленна только у числа, справочник заполняется
            // выбором из списка. Запрещаем лишние поля, а не молча
            // отбрасываем их: молчаливая нормализация выглядит как баг —
            // свойство создано, а единица «не сохранилась».
            'unit_id' => [
                'nullable',
                'integer',
                Rule::exists('unit', 'id')->where('user_id', $userId),
                Rule::prohibitedIf(fn (): bool => ! $this->isNumeric()),
            ],
            'dictionary_id' => [
                'nullable',
                'integer',
                Rule::exists('dictionary', 'id')->where('user_id', $userId),
                Rule::requiredIf(fn (): bool => $this->isDictionary()),
                Rule::prohibitedIf(fn (): bool => ! $this->isDictionary()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_id.prohibited'      => 'Единица измерения бывает только у числового свойства',
            'dictionary_id.prohibited' => 'Справочник бывает только у свойства типа «Из справочника»',
            'dictionary_id.required'  => 'Выберите справочник для свойства типа «Из справочника»',
        ];
    }

    private function isNumeric(): bool
    {
        return in_array($this->input('type'), [PropertyType::Int->value, PropertyType::Float->value], true);
    }

    private function isDictionary(): bool
    {
        return $this->input('type') === PropertyType::Dictionary->value;
    }
}
