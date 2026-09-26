<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDictionaryRequest;
use App\Http\Requests\StoreDictionaryValueRequest;
use App\Http\Requests\UpdateDictionaryRequest;
use App\Http\Requests\UpdateDictionaryValueRequest;
use App\Http\Resources\DictionaryResource;
use App\Http\Resources\DictionaryValueResource;
use App\Models\Dictionary;
use App\Models\DictionaryValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class DictionaryController extends Controller
{
    public function index(): ResourceCollection
    {
        return DictionaryResource::collection(
            Dictionary::with('user')->withCount('values')->orderBy('id')->paginate()
        );
    }

    /**
     * Справочники вместе со значениями: селекту значения свойства из
     * справочника нужен весь список, и без values он был бы пустым.
     */
    public function all(): ResourceCollection
    {
        return DictionaryResource::collection(
            Dictionary::with('values')->withCount('values')->orderBy('id')->get()
        );
    }

    public function get(Dictionary $model): DictionaryResource
    {
        return new DictionaryResource($model->load(['values', 'user'])->loadCount('values'));
    }

    public function post(StoreDictionaryRequest $request): JsonResponse
    {
        $dictionary = Dictionary::create($request->validated());

        return (new DictionaryResource($dictionary->load(['values', 'user'])->loadCount('values')))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateDictionaryRequest $request, Dictionary $model): DictionaryResource
    {
        $model->update($request->validated());

        return new DictionaryResource($model->load(['values', 'user'])->loadCount('values'));
    }

    public function delete(Dictionary $model): JsonResponse
    {
        // Значения удаляем обходом, а не каскадом: каскад стёр бы их мимо
        // обзервера, и в журнале не осталось бы следов, что ушли именно они.
        DB::transaction(function () use ($model): void {
            foreach ($model->values as $value) {
                $value->delete();
            }

            $model->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Значения справочника живут под справочником, а не сами по себе:
     * GET /api/dictionary/{model}/values
     */
    public function values(Dictionary $model): ResourceCollection
    {
        return DictionaryValueResource::collection(
            $model->values()->orderBy('id')->get()
        );
    }

    public function storeValue(StoreDictionaryValueRequest $request, Dictionary $model): JsonResponse
    {
        $value = $model->values()->create(['title' => $request->validated('title')]);

        return (new DictionaryValueResource($value))
            ->response()
            ->setStatusCode(201);
    }

    public function updateValue(UpdateDictionaryValueRequest $request, Dictionary $model, DictionaryValue $value): DictionaryValueResource
    {
        $this->ensureBelongsTo($value, $model);

        $value->update($request->validated());

        return new DictionaryValueResource($value);
    }

    public function deleteValue(Dictionary $model, DictionaryValue $value): JsonResponse
    {
        $this->ensureBelongsTo($value, $model);

        $value->delete();

        return response()->json(null, 204);
    }

    /**
     * У DictionaryValue нет своего user_id и нет скоупа (владение выводится
     * из справочника), поэтому подстановка модели в маршруте нашла бы и
     * чужое значение. Сверяем с родителем: и {model}, и {value} указывают
     * на строки одного пользователя только если значения действительно
     * принадлежат этому справочнику.
     */
    private function ensureBelongsTo(DictionaryValue $value, Dictionary $dictionary): void
    {
        abort_unless((int) $value->dictionary_id === (int) $dictionary->id, 404);
    }
}
