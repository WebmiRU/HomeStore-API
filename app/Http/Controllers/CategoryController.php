<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PropertyResource;
use App\Models\Category;
use App\Services\ItemPropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(): ResourceCollection
    {
        return CategoryResource::collection(
            Category::with(['parent', 'user'])
                ->withCount('items')
                ->orderBy('id')
                ->paginate()
        );
    }

    /**
     * Всё дерево без пагинации: список селекта в форме предмета должен
     * показать все категории разом, постраничный обрезанный список там
     * просто нельзя было бы выбрать.
     */
    public function all(): ResourceCollection
    {
        // Счётчик предметов здесь тоже нужен: список категорий показывается
        // деревом, и без него в строке нечего было бы написать.
        return CategoryResource::collection(
            Category::with('parent')->withCount('items')->orderBy('id')->get()
        );
    }

    public function get(Category $model): CategoryResource
    {
        return new CategoryResource($model->load(['parent', 'user'])->loadCount('items'));
    }

    /**
     * Набор свойств категории — вычисленный по уже заполненным значениям.
     * GET /api/category/{model}/properties
     */
    public function properties(ItemPropertyService $service, Category $model): ResourceCollection
    {
        return PropertyResource::collection($service->forCategory($model));
    }

    public function post(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return (new CategoryResource($category->load(['parent', 'user'])->loadCount('items')))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateCategoryRequest $request, Category $model): CategoryResource
    {
        $data = $request->validated();

        $this->guardParent($data['parent_id'] ?? $model->parent_id, $model);

        $model->update($data);

        return new CategoryResource($model->load(['parent', 'user'])->loadCount('items'));
    }

    /**
     * Ветку удаляет обходом, а не каскадом в БД: каскад стёр бы поддерево
     * мимо обзервера, и в журнале осталась бы одна запись об удалении
     * верхней категории вместо записей обо всём, что пропало.
     */
    public function delete(Category $model): JsonResponse
    {
        DB::transaction(function () use ($model): void {
            $ids = array_reverse(array_merge([$model->id], $model->descendantIds()));

            foreach ($ids as $id) {
                Category::find($id)?->delete();
            }
        });

        return response()->json(null, 204);
    }

    /**
     * Родителем нельзя назначить саму категорию или её же потомка: дерево
     * перестало бы быть деревом, а обход потомков в Category::descendantIds()
     * и раскрытие дерева в интерфейсе зациклились бы.
     */
    private function guardParent(?int $parentId, Category $model): void
    {
        if ($parentId === null) {
            return;
        }

        abort_if($parentId === $model->id, 422, 'Категория не может быть родителем самой себя');

        abort_if(
            in_array($parentId, $model->descendantIds(), true),
            422,
            'Нельзя вложить категорию в её же вложенную категорию'
        );
    }
}
