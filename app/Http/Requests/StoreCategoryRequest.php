<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'     => ['required', 'string', 'max:500', $this->uniqueAmongSiblings()],
            'parent_id' => [
                'nullable',
                'integer',
                // exists без user_id пропустил бы родителя из чужого каталога:
                // правила exists идут в БД мимо скоупов моделей.
                Rule::exists('category', 'id')->where('user_id', CurrentUser::id()),
            ],
        ];
    }

    /**
     * Название уникально среди соседей, а не во всём каталоге: «Зимнее» вполне
     * может встретиться и в «Одежде», и в «Шинном деле». Готового правила
     * unique под такой случай нет — COALESCE(parent_id, 0) в индексе
     * переводит запрос на сравнение в SQL.
     */
    private function uniqueAmongSiblings(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $query = DB::table('category')
                ->where('user_id', CurrentUser::id())
                ->where('title', trim((string) $value));

            $parentId = $this->input('parent_id');

            $parentId === null
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', (int) $parentId);

            if ($query->exists()) {
                $fail('В этом разделе уже есть категория с таким названием');
            }
        };
    }
}
