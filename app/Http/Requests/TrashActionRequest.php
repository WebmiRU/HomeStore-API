<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Общий запрос для действий в корзине: восстановление и окончательное
 * удаление отличаются только тем, что делают, а принимают одно и то же.
 */
class TrashActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Ограничение сверху: тело на сотни id — это уже не «снял галочки»,
            // а заливка, и такой запрос имеет смысл отклонять.
            'ids'  => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Не выбрано ни одной записи',
            'ids.max'      => 'За раз можно выбрать не больше 200 записей',
        ];
    }
}
