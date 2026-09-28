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
            'file.required' => __('Не выбран файл изображения'),
            'file.image'    => __('Файл не является изображением'),
            'file.mimes'    => __('Разрешены форматы: PNG, JPEG, WEBP, AVIF'),
            'file.max'      => __('Размер файла не должен превышать 20 МБ'),
        ];
    }
}