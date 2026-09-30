<?php

namespace App\Http\Requests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'     => ['sometimes', 'string', 'max:500', $this->uniqueAmongSiblings()],
            // Фотографии приходят вместе с категорией: завести её сразу с
            // картинками, а не возвращаться за ними вторым заходом.
            'images'        => ['sometimes', 'array', 'max:50'],
            'images.*.id'   => ['required', 'integer', 'min:1'],
            'images.*.alt'  => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('category', 'id')->where('user_id', CurrentUser::id()),
            ],
        ];
    }

    private function uniqueAmongSiblings(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $model = $this->route('model');

            $query = DB::table('category')
                ->where('user_id', CurrentUser::id())
                // Удалённая категория не занимает имя: уникальный индекс
                // частичный, и проверка должна вести себя так же, иначе
                // удалил бы «Манометры» — и больше не смог бы завести такие.
                ->whereNull('deleted_at')
                ->where('title', trim((string) $value));

            // parent_id объявлен как sometimes, и когда его не прислали, родитель
            // не меняется — проверять надо против текущего, а не против null.
            $parentId = $this->has('parent_id') ? $this->input('parent_id') : $model?->parent_id;

            $parentId === null
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', (int) $parentId);

            if ($model !== null) {
                $query->where('id', '<>', $model->id);
            }

            if ($query->exists()) {
                $fail('В этом разделе уже есть категория с таким названием');
            }
        };
    }
}
