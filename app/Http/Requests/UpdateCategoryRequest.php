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
