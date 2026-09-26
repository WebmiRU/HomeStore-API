<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_short' => [
                'sometimes',
                'string',
                'max:16',
                Rule::unique('unit', 'title_short')
                    ->where('user_id', CurrentUser::id())
                    ->ignore($this->route('model')),
            ],
            'title_full'  => ['sometimes', 'string', 'max:255'],
        ];
    }
}
