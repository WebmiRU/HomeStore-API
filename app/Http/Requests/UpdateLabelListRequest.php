<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabelListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'string', 'max:500', 'unique:label_list,title'],
            'label_preset_id' => ['sometimes', 'integer', 'exists:label_preset,id'],
        ];
    }
}
