<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = CurrentUser::id();

        return [
            'title'       => ['sometimes', 'string', 'max:500'],
            'title_print' => ['nullable', 'string', 'max:500'],
            'store_id'    => ['nullable', 'integer', 'exists:store,id'],
            'code'        => ['nullable', 'string', 'min:8', 'max:256'],
            'quantity'    => ['sometimes', 'nullable', 'integer'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('category', 'id')->where('user_id', $userId),
            ],

            // Раздела properties в теле может не быть вовсе — тогда значения
            // не трогаются. Пустой массив — это явное «очистить», и отличать
            // одно от другого приходится в ItemController.
            'properties'                    => ['sometimes', 'nullable', 'array'],
            'properties.*.property_id'       => ['required', 'integer'],
            'properties.*.values'            => ['sometimes', 'array'],
            'properties.*.values.*'          => ['nullable', 'array'],
            'properties.*.values.*.value'    => ['nullable', 'string', 'max:1000'],
            'properties.*.values.*.dictionary_value_id' => ['nullable', 'integer'],
        ];
    }
}
