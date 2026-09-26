<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Короткое обозначение показывается в форме предмета прямо у
            // значения, поэтому держим его коротким буквально, а не
            // соглашением: «кг» рядом с числом не должно ломать строку.
            'title_short' => [
                'required',
                'string',
                'max:16',
                Rule::unique('unit', 'title_short')->where('user_id', CurrentUser::id()),
            ],
            'title_full'  => ['required', 'string', 'max:255'],
        ];
    }
}
