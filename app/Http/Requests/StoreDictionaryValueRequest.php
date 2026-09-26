<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Справочник приходит из маршрута (/dictionary/{model}/value), поэтому
 * dictionary_id в теле запроса нет: иначе значение можно было бы перенести
 * в чужой справочник одной правкой тела запроса.
 */
class StoreDictionaryValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:500',
                Rule::unique('dictionary_value', 'title')
                    ->where('dictionary_id', $this->route('model')->id),
            ],
        ];
    }
}
