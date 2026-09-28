<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Логотип поставщикя — одна картинка, поэтому правила те же, что у
 * аватара пользователя: тот идёт через Image::fromUploadedFile() и кладётся
 * в общий каталог изображений.
 */
class StoreVendorLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp,avif'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('Не выбран файл логотипа'),
            'file.image'    => __('Файл не является изображением'),
            'file.mimes'    => __('Разрешены форматы: PNG, JPEG, WEBP, AVIF'),
            'file.max'      => __('Размер файла не должен превышать 5 МБ'),
        ];
    }
}
