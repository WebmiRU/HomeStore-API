<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlankLabelListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label_preset_id' => ['required', 'integer', 'exists:label_preset,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'label_preset_id.required' => 'Не указан шаблон этикеток',
            'label_preset_id.exists'   => 'Шаблон этикеток не найден',
        ];
    }
}
