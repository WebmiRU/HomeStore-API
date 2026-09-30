<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDictionaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:500',
                Rule::unique('dictionary', 'title')->where('user_id', CurrentUser::id())
                ->whereNull('deleted_at'),
            ],

            /*
             * Значения приходят вместе со справочником.
             *
             * Справочник без значений бесполезен: он и создан ради них, и
             * раньше завести его можно было только названием — значения
             * приходилось вбивать вторым заходом, уже на странице правки.
             * Требование уникальности названия значения теперь проверяется
             * внутри одного справочника: два одинаковых значения имеют смысл
             * только в разных справочниках, а внутри одного это опечатка.
             */
            'values' => ['sometimes', 'array', 'max:200'],
            'values.*' => ['required', 'string', 'max:500', 'distinct'],
        ];
    }
}
