<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = CurrentUser::id();

        return [
            'title'       => ['required', 'string', 'max:500'],
            'title_print' => ['nullable', 'string', 'max:500'],
            'store_id'    => ['nullable', 'integer', 'exists:store,id'],
            'code'        => ['nullable', 'string', 'min:8', 'max:256'],
            'quantity'    => ['nullable', 'integer'],

            // Категория своя, не чужая: предмет, положенный в чужое дерево,
            // показывался бы в чужом разделе каталога, а его набор свойств
            // считался бы по значениям чужого дерева.
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('category', 'id')->where('user_id', $userId),
            ],

            // Сами значения свойств проверяются не здесь, а в
            // ItemPropertyService: тип свойства и его справочник лежат в БД,
            // а правила FormRequest не умеют смотреть в соседние строки.
            'properties'                    => ['nullable', 'array'],
            'properties.*.property_id'       => ['required', 'integer'],
            'properties.*.values'            => ['sometimes', 'array'],
            'properties.*.values.*'          => ['nullable', 'array'],
            'properties.*.values.*.value'    => ['nullable', 'string', 'max:1000'],
            'properties.*.values.*.dictionary_value_id' => ['nullable', 'integer'],
        ];
    }
}
