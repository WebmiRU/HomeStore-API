<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'   => ['sometimes', 'string', 'max:500'],
            'user_id' => ['sometimes', 'integer', 'exists:user,id'],
        ];
    }
}