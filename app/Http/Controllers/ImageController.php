<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderImagesRequest;
use App\Http\Requests\StoreImageRequest;
use App\Http\Requests\UpdateImageAltRequest;
use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Models\Item;
use App\Models\Store;
use App\Services\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    public function storeForItem(StoreImageRequest $request, Item $model): JsonResponse
    {
        $this->requireEdit($model);

        return (new ImageResource($this->store($request, $model)))
            ->response()
            ->setStatusCode(201);
    }

    public function storeForStore(StoreImageRequest $request, Store $model): JsonResponse
    {
        $this->requireEdit($model);

        return (new ImageResource($this->store($request, $model)))
            ->response()
            ->setStatusCode(201);
    }

    private function store(StoreImageRequest $request, Item|Store $model): Image
    {
        $file = $request->file('file');
        $sha256 = hash_file('sha256', $file->getRealPath());

        $image = Image::query()->where('sha256', $sha256)->first();

        if ($image === null) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            if ($extension === '') {
                $extension = strtolower((string) Str::after($file->getMimeType(), '/'));
            }
            $path = 'src/' . $sha256 . '.' . $extension;

            Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

            try {
                $image = Image::create([
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime'          => $file->getMimeType(),
                    'sha256'        => $sha256,
                ]);
            } catch (QueryException $e) {
                // Параллельная загрузка такого же файла уже создала запись
                if ($e->getCode() !== '23505') {
                    throw $e;
                }
                $image = Image::query()->where('sha256', $sha256)->firstOrFail();
            }
        }

        $model->images()->attach($image->id, [
            'alt'    => null,
            'weight' => $this->nextWeight($model),
        ]);

        return $model->images()->where('image.id', $image->id)->first();
    }

    private function nextWeight(Item|Store $model): int
    {
        $currentMax = (int) $model->images()->max('image_m2m_' . ($model instanceof Store ? 'store' : 'item') . '.weight');

        return $currentMax + 1;
    }

    public function updateAltForItem(UpdateImageAltRequest $request, Item $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        $model->images()->updateExistingPivot($image->id, ['alt' => $request->input('alt')]);

        return (new ImageResource($this->withPivot($model, $image)))->response();
    }

    public function updateAltForStore(UpdateImageAltRequest $request, Store $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        $model->images()->updateExistingPivot($image->id, ['alt' => $request->input('alt')]);

        return (new ImageResource($this->withPivot($model, $image)))->response();
    }

    public function reorderForItem(ReorderImagesRequest $request, Item $model): JsonResponse
    {
        $this->requireEdit($model);
        $this->reorder($model, $request->validated('ids'));

        return response()->json(['ids' => $request->validated('ids')]);
    }

    public function reorderForStore(ReorderImagesRequest $request, Store $model): JsonResponse
    {
        $this->requireEdit($model);
        $this->reorder($model, $request->validated('ids'));

        return response()->json(['ids' => $request->validated('ids')]);
    }

    private function reorder(Item|Store $model, array $ids): void
    {
        $attachedIds = $model->images()->pluck('image.id')->all();

        $position = 0;
        foreach ($ids as $imageId) {
            if (! in_array($imageId, $attachedIds, true)) {
                continue;
            }

            $model->images()->updateExistingPivot($imageId, ['weight' => $position]);
            $position++;
        }

        // Любые картинки, не упомянутые в списке, отправляем в конец
        foreach ($attachedIds as $attachedId) {
            if (! in_array($attachedId, $ids)) {
                $model->images()->updateExistingPivot($attachedId, ['weight' => $position]);
                $position++;
            }
        }
    }

    private function withPivot(Item|Store $model, Image $image): Image
    {
        return $model->images()->where('image.id', $image->id)->first();
    }

    public function removeForItem(Item $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        $model->images()->detach($image->id);

        return response()->json(null, 204);
    }

    public function removeForStore(Store $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        $model->images()->detach($image->id);

        return response()->json(null, 204);
    }

    private function requireEdit(Item|Store $model): void
    {
        abort_unless(app(AccessService::class)->canEdit($model), 403);
    }
}