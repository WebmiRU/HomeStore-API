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

            // Правило для locale обязательно: без него validated() отбрасывал
            // поле, и язык молча оставался прежним — настройка выглядела
            // сохранённой, но не менялась.
            'locale'          => ['sometimes', 'string', Rule::in(Option::locales())],

            'theme'           => ['sometimes', 'string', Rule::in(Option::themes())],

            'accent'          => ['sometimes', 'string', Rule::in(Option::accents())],

            'show_code_block' => ['sometimes', 'boolean'],

            'remember_operation_mode' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_order.array'    => __('Порядок пунктов меню — список ключей.'),
            'menu_order.max'      => __('Пунктов меню больше двухсот быть не может.'),
            'menu_order.*.required' => __('Пункт меню не может быть пустым.'),
            'menu_order.*.max'    => __('Ключ пункта меню длиннее 64 символов — такого быть не должно.'),
            'menu_order.*.string' => __('Ключ пункта меню — строка.'),
            'menu_hidden.array'   => __('Скрытые пункты меню — список ключей.'),
            'menu_hidden.max'     => __('Пунктов меню больше двухсот быть не может.'),
            'menu_hidden.*.required' => __('Пункт меню не может быть пустым.'),
            'menu_hidden.*.max'   => __('Ключ пункта меню длиннее 64 символов — такого быть не должно.'),
            'menu_hidden.*.string' => __('Ключ пункта меню — строка.'),
            'operation_mode.in'   => __('Режим работы может быть только «Поиск», «Пополнение» или «Списание».'),
            'show_code_block.boolean' => __('Показывать блок «Код» — да или нет.'),
            'remember_operation_mode.boolean' => __('Запоминать режим работы — да или нет.'),
            'theme.in'          => __('Тема может быть только «Тёмная», «Светлая» или «Как в системе».'),
            'accent.in'         => __('Акцент может быть только «Зелёный», «Пурпурный», «Синий» или «Янтарный».'),
        ];
    }
}
