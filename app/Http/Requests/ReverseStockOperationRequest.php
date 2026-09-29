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

            // У обычной строки количество возврата — целые штуки, у строки
            // частичного расхода — доля свойства, и она дробная: вернуть
            // 100 мл из списанных 300 законно. Поле amount шлёт клиент для
            // таких строк, quantity остаётся для прежних и обратной
            // совместимости.
            'rows.*.quantity' => ['required', 'integer', 'min:1'],
            'rows.*.amount'   => ['sometimes', 'numeric', 'min:0'],

            'comment'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
