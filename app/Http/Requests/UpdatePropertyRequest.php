<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = CurrentUser::id();
        $model = $this->route('model');

        return [
            'title'  => [
                'sometimes',
                'string',
                'max:500',
                Rule::unique('property', 'title')
                    ->where('user_id', $userId)
                    ->ignore($model),
            ],
            // Тип можно поменять только когда у свойства ещё нет значений:
            // иначе значения, заполненные как текст, остались бы в колонке
            // для чисел. Проверяется в PropertyController::put().
            'type'   => ['sometimes', Rule::enum(PropertyType::class)],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('property_group', 'id')->where('user_id', $userId),
            ],
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
            'unit_id.prohibited'       => 'Единица измерения бывает только у числового свойства',
            'dictionary_id.prohibited' => 'Справочник бывает только у свойства типа «Из справочника»',
            'dictionary_id.required'   => 'Выберите справочник для свойства типа «Из справочника»',
        ];
    }

    /**
     * Тип приходит не всегда, и тогда решение принимает текущий тип
     * свойства — иначе смена одного поля сбросила бы соседние.
     */
    private function currentType(): ?string
    {
        $model = $this->route('model');

        if ($this->has('type')) {
            return $this->input('type');
        }

        return $model?->type?->value;
    }

    private function isNumeric(): bool
    {
        return in_array($this->currentType(), [PropertyType::Int->value, PropertyType::Float->value], true);
    }

    private function isDictionary(): bool
    {
        return $this->currentType() === PropertyType::Dictionary->value;
    }
}
