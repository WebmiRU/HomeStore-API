<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouse,id'],
            'user_id'      => ['required', 'integer', 'exists:user,id'],
            'rights'       => ['required', 'array', 'min:1'],
            'rights.*'     => [Rule::in(['view', 'create', 'edit', 'delete'])],
        ];
    }
}