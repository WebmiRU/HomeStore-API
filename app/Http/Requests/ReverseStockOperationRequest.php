<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReverseStockOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Возврат может быть выборочным: часть строк и часть количества
            // внутри строки. Поэтому здесь именно список, а не одна сумма.
            'rows'           => ['required', 'array', 'min:1'],
            'rows.*.row_id'  => ['required', 'integer', 'min:1'],
            'rows.*.quantity' => ['required', 'integer', 'min:1'],

            'comment'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
