<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Общий фильтр для страницы чистки кодов: сводка, предпросмотр и удаление
 * принимают один и тот же параметр, поэтому предпросмотр и удаление всегда
 * смотрят на одну и ту же выборку.
 */
class OrphanedCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'older_than_days' => ['sometimes', 'integer', 'min:0', 'max:36500'],
        ];
    }

    public function messages(): array
    {
        return [
            'older_than_days.integer' => 'Число дней должно быть целым',
            'older_than_days.min'      => 'Число дней не может быть отрицательным',
        ];
    }

    /**
     * Граница возраста в днях. 0 — все коды независимо от возраста.
     */
    public function days(): int
    {
        return (int) ($this->validated('older_than_days') ?? 0);
    }
}
