<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png,jpeg,jpg,webp,avif', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Не выбран файл изображения',
            'file.image'    => 'Файл не является изображением',
            'file.mimes'    => 'Разрешены форматы: PNG, JPEG, WEBP, AVIF',
            'file.max'      => 'Размер файла не должен превышать 20 МБ',
        ];
    }
}