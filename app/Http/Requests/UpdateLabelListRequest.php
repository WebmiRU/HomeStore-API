<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'string', 'max:500', Rule::unique('label_list', 'title')
                    ->ignore($this->route('model'))
                    ->whereNull('deleted_at')],
            'label_preset_id' => ['sometimes', 'integer', 'exists:label_preset,id'],
            'print_all_codes' => ['sometimes', 'boolean'],
        ];
    }
}
