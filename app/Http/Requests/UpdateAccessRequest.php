<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rights'   => ['required', 'array', 'min:1'],
            'rights.*' => [Rule::in(['view', 'create', 'edit', 'delete'])],
        ];
    }
}