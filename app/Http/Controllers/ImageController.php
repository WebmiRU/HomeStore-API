<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\ReorderImagesRequest;
use App\Http\Requests\StoreImageRequest;
use App\Http\Requests\UpdateImageAltRequest;
use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Models\Item;
use App\Models\Store;
use App\Services\AccessService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class ImageController extends Controller
{
    public function __construct(private readonly AuditLogService $logs)
    {
    }

    public function storeForItem(StoreImageRequest $request, Item $model): JsonResponse
    {
        $this->requireEdit($model);

        $image = $this->store($request, $model);

        $this->logImage('item', AuditAction::ImageAttached, $model, $image);

        return (new ImageResource($image))->response()->setStatusCode(201);
    }

    public function storeForStore(StoreImageRequest $request, Store $model): JsonResponse
    {
        $this->requireEdit($model);

        $image = $this->store($request, $model);

        $this->logImage('store', AuditAction::ImageAttached, $model, $image);

        return (new ImageResource($image))->response()->setStatusCode(201);
    }

    private function store(StoreImageRequest $request, Item|Store $model): Image
    {
        $image = Image::fromUploadedFile($request->file('file'));

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
        $this->logImage('item', AuditAction::ImageAltUpdated, $model, $image, [
            'alt' => $request->input('alt'),
        ]);

        return (new ImageResource($this->withPivot($model, $image)))->response();
    }

    public function updateAltForStore(UpdateImageAltRequest $request, Store $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        $model->images()->updateExistingPivot($image->id, ['alt' => $request->input('alt')]);
        $this->logImage('store', AuditAction::ImageAltUpdated, $model, $image, [
            'alt' => $request->input('alt'),
        ]);

        return (new ImageResource($this->withPivot($model, $image)))->response();
    }

    public function reorderForItem(ReorderImagesRequest $request, Item $model): JsonResponse
    {
        $this->requireEdit($model);
        $this->reorder($model, $request->validated('ids'));
        $this->logImage('item', AuditAction::ImageReordered, $model, null, [
            'ids' => $request->validated('ids'),
        ]);

        return response()->json(['ids' => $request->validated('ids')]);
    }

    public function reorderForStore(ReorderImagesRequest $request, Store $model): JsonResponse
    {
        $this->requireEdit($model);
        $this->reorder($model, $request->validated('ids'));
        $this->logImage('store', AuditAction::ImageReordered, $model, null, [
            'ids' => $request->validated('ids'),
        ]);

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
        $this->logImage('item', AuditAction::ImageDetached, $model, $image);

        return response()->json(null, 204);
    }

    public function removeForStore(Store $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        $model->images()->detach($image->id);
        $this->logImage('store', AuditAction::ImageDetached, $model, $image);

        return response()->json(null, 204);
    }

    private function requireEdit(Item|Store $model): void
    {
        abort_unless(app(AccessService::class)->canEdit($model), 403);
    }

    private function logImage(
        string $kind,
        AuditAction $action,
        Item|Store $model,
        ?Image $image,
        array $extra = [],
    ): void {
        $payload = array_merge([
            'image_id' => $image?->id,
            'sha256'   => $image?->sha256,
            'title'    => $model->title,
        ], $extra);

        $this->logs->record(
            $action,
            $kind === 'item' ? 'item_id' : 'store_id',
            (int) $model->id,
            (int) ($model->user_id ?? 0),
            $payload,
        );
    }
}