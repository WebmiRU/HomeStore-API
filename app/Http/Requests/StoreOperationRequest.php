<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'              => ['required', 'string', 'in:operation.replenish,operation.writeoff'],
            'payload'           => ['required', 'array', 'min:1'],
            'payload.*.code'    => ['required', 'string', 'min:8', 'max:256'],
            'payload.*.item_id' => ['nullable', 'integer', 'min:1'],
            'payload.*.quantity' => ['required', 'integer', 'min:1'],

            // Комментарий один на операцию, а не на строку: пользователь
            // сканирует пачку кодов и объясняет её одним текстом — «куда
            // списали». Необязателен: заполнять должен быть возможностью.
            'comment'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
