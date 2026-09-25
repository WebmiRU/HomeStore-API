<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Список id изображений обязателен',
            'ids.array'    => 'Список id изображений должен быть массивом',
            'ids.*.integer' => 'id изображений должны быть целыми числами',
            'ids.*.distinct' => 'id изображений не должны повторяться',
        ];
    }
}