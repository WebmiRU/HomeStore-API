<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * Фактический остаток, а не дельта. Человек знает, сколько на руках,
             * и не обязан вычитать из того, что показала система, — при расхождении
             * именно его число и есть истина. Дельту считает сервер: иначе
             * ошибку в арифметике не отличить от ошибки в остатке.
             */
            'payload'              => ['required', 'array', 'min:1', 'max:50'],
            'payload.*.item_id'    => ['required', 'integer', 'min:1'],
            'payload.*.quantity'   => ['sometimes', 'integer', 'min:0'],

            'payload.*.properties'   => ['sometimes', 'array'],
            'payload.*.properties.*.property_id' => ['required', 'integer', 'min:1'],
            'payload.*.properties.*.actual'      => ['required', 'numeric', 'min:0'],

            // Причина необязательна: чаще всего её и не знают, а спрашивать
            // каждый раз об одном и том же — значит заставлять выбирать из
            // списка. Кто знает, тот напишет, и через полгода будет видно.
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payload.*.properties.*.actual.required' => __('Укажите, сколько на самом деле'),
        ];
    }
}
