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
            'quantity'    => ['sometimes', 'nullable', 'integer'],

            // Пометка «списывать по коду»: код, по которому списали,
            // высвобождается и может быть наклеен на другую вещь.
            'release_code_on_writeoff' => ['sometimes', 'boolean'],

            // Кодов у предмета может быть несколько, см. StoreItemRequest.
            'codes'       => ['sometimes', 'nullable', 'array', 'max:100'],
            'codes.*'     => ['nullable', 'string', 'min:8', 'max:256'],

            // Прежнее имя одного кода, оставлено для совместимости.
            'code'        => ['nullable', 'string', 'min:8', 'max:256'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('category', 'id')->where('user_id', $userId),
            ],

            // Поставщик свой, не чужой: предмет с чужим поставщиком
            // показывался бы в чужом разделе каталога.
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('vendor', 'id')->where('user_id', $userId),
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

            // Настройки частичного списания: какие свойства предмета
            // расходуются частями. Тип свойства и совместимость с режимом
            // «списывать по коду» проверяются в PartialWriteoff — это бизнес-правило,
            // а не формат запроса, и FormRequest о нём не знает.
            'partial_properties'               => ['sometimes', 'nullable', 'array', 'max:50'],
            'partial_properties.*.property_id' => ['required', 'integer'],
            'partial_properties.*.step'        => ['sometimes', 'numeric', 'min:0'],

            // Без правила для is_full_reason поле отбрасывалось при валидации,
            // и снятая галочка «обнуление — признак пустого» молча
            // сохранялась как включённая: предмет считали всегда готовым
            // кончиться по любому из отмеченных свойств.
            'partial_properties.*.is_full_reason' => ['sometimes', 'boolean'],
            'partial_properties.*.sort'            => ['sometimes', 'integer'],

            // Картинки, загруженные до создания сущности. Клиент грузит их
            // заранее — сущности ещё нет, и привязать не к чему, — а здесь
            // перечисляет, какие из загруженных принадлежат этой.
            'images'                => ['sometimes', 'array', 'max:50'],
            'images.*.id'           => ['required', 'integer', 'min:1'],
            'images.*.alt'          => ['nullable', 'string', 'max:255'],
        ];
    }
}
