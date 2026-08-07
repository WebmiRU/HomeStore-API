<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabelListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:500', 'unique:label_list,title'],
            'label_preset_id' => ['required', 'integer', 'exists:label_preset,id'],
        ];
    }
}
