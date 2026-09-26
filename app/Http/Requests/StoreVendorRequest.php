<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Название уникально в пределах каталога владельца, а не во всём
            // мире: два разных человека спокойно заводят своего «Bosch».
            'title' => [
                'required',
                'string',
                'max:500',
                Rule::unique('vendor', 'title')->where('user_id', CurrentUser::id()),
            ],
            // Описание в форме — textarea, поэтому длиннее, чем название.
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Обрезка краёв делается до валидации, а не только в модели: правило
     * unique сравнивает сырое значение, и «Bosch » прошло бы проверку
     * уникальности, чтобы упереться в индекс уже при вставке.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('title')) {
            $this->merge(['title' => trim((string) $this->input('title'))]);
        }
    }
}
