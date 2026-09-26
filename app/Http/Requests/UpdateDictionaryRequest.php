<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDictionaryRequest extends FormRequest
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
                Rule::unique('dictionary', 'title')
                    ->where('user_id', CurrentUser::id())
                    ->ignore($this->route('model')),
            ],
        ];
    }
}
