<?php

namespace App\Http\Requests;

use App\Models\Option;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Проверка настроек при сохранении.
 *
 * Поля необязательные: клиент шлёт только то, что человек поменял, а
 * остальное остаётся как есть.
 *
 * Ключи меню проверяются на вид, а не на состав: какие пункты бывают, знает
 * меню приложения. Ключ, которого в меню нет, клиент игнорирует сам.
 */
class UpdateOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_order'      => ['sometimes', 'array', 'max:200'],
            'menu_order.*'    => ['required', 'string', 'max:64'],

            'menu_hidden'     => ['sometimes', 'array', 'max:200'],
            'menu_hidden.*'   => ['required', 'string', 'max:64'],

            'operation_mode'  => ['sometimes', Rule::in([
                Option::MODE_SEARCH,
                Option::MODE_REPLENISH,
                Option::MODE_WRITEOFF,
            ])],

            'show_code_block' => ['sometimes', 'boolean'],

            'remember_operation_mode' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_order.array'    => 'Порядок пунктов меню — список ключей.',
            'menu_order.max'      => 'Пунктов меню больше двухсот быть не может.',
            'menu_order.*.required' => 'Пункт меню не может быть пустым.',
            'menu_order.*.max'    => 'Ключ пункта меню длиннее 64 символов — такого быть не должно.',
            'menu_order.*.string' => 'Ключ пункта меню — строка.',
            'menu_hidden.array'   => 'Скрытые пункты меню — список ключей.',
            'menu_hidden.max'     => 'Пунктов меню больше двухсот быть не может.',
            'menu_hidden.*.required' => 'Пункт меню не может быть пустым.',
            'menu_hidden.*.max'   => 'Ключ пункта меню длиннее 64 символов — такого быть не должно.',
            'menu_hidden.*.string' => 'Ключ пункта меню — строка.',
            'operation_mode.in'   => 'Режим работы может быть только «Поиск», «Пополнение» или «Списание».',
            'show_code_block.boolean' => 'Показывать блок «Код» — да или нет.',
            'remember_operation_mode.boolean' => 'Запоминать режим работы — да или нет.',
        ];
    }
}
