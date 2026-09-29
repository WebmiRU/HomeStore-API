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
            // Количество штуками. У предмета с частичным списанием оно не
            // значит ничего: расход идёт по свойствам в parts, а количество
            // меняется само, когда опустела штука. Поле необязательное там,
            // где есть parts, и обязательное там, где parts нет, — это
            // проверяется в OperationController, где уже известен предмет.
            'payload.*.quantity' => ['sometimes', 'integer', 'min:1'],

            // Расход по свойствам: сколько списать или пополнить по каждому.
            // Несколько свойств в строке — законный случай: у мешка картошка и
            // рис расходуются вместе, за один скан уходит и то и другое.
            'payload.*.parts' => ['sometimes', 'array', 'min:1', 'max:20'],
            'payload.*.parts.*.property_id' => ['required', 'integer'],
            'payload.*.parts.*.amount' => ['required', 'numeric', 'min:0'],

            // Комментарий один на операцию, а не на строку: пользователь
            // сканирует пачку кодов и объясняет её одним текстом — «куда
            // списали». Необязателен: заполнять должен быть возможностью.
            'comment'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
