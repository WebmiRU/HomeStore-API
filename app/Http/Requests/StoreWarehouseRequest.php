<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:500'],
            'user_id' => ['required', 'integer', 'exists:user,id'],
            // Картинки, загруженные до создания: сущности ещё нет, привязать
            // не к чему, и файл ждёт своего владельца до сохранения.
            'images'                => ['sometimes', 'array', 'max:50'],
            'images.*.id'           => ['required', 'integer', 'min:1'],
            'images.*.alt'          => ['nullable', 'string', 'max:255'],
        ];
    }
}