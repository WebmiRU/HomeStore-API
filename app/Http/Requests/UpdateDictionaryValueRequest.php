<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDictionaryValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'string',
                'max:500',
                Rule::unique('dictionary_value', 'title')
                    ->where('dictionary_id', $this->route('model')->dictionary_id)
                    ->ignore($this->route('value')),
            ],
        ];
    }
}
