<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:500'],
            'title_print' => ['nullable', 'string', 'max:500'],
            'store_id'    => ['nullable', 'integer', 'exists:store,id'],
        ];
    }
}
