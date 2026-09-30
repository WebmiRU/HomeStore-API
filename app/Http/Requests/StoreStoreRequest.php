<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
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
            'parent_id'   => ['nullable', 'integer', 'exists:store,id'],
            'warehouse_id'=> ['nullable', 'integer', 'exists:warehouse,id'],
            'code'        => ['nullable', 'string', 'min:8', 'max:256'],
            // Картинки, загруженные до создания: сущности ещё нет, привязать
            // не к чему, и файл ждёт своего владельца до сохранения.
            'images'                => ['sometimes', 'array', 'max:50'],
            'images.*.id'           => ['required', 'integer', 'min:1'],
            'images.*.alt'          => ['nullable', 'string', 'max:255'],
        ];
    }
}
