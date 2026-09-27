<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabelListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:500', Rule::unique('label_list', 'title')->whereNull('deleted_at')],
            'label_preset_id' => ['required', 'integer', 'exists:label_preset,id'],
            'print_all_codes' => ['sometimes', 'boolean'],
        ];
    }
}
