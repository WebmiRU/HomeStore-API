<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\ReorderImagesRequest;
use App\Http\Requests\StoreImageRequest;
use App\Http\Requests\UpdateImageAltRequest;
use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Models\ImageOwner;
use App\Models\Item;
use App\Models\Store;
use App\Models\Warehouse;
use App\Services\AccessService;
use App\Services\AuditLogService;
use App\Services\ImageAttach;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImageController extends Controller
{
    public function __construct(
        private readonly AuditLogService $logs,
        private readonly ImageAttach $attach,
    ) {
    }

    /**
     * Загрузка без привязки — для формы создания, где сущности ещё нет.
     *
     * Картинка появляется в базе сразу и ждёт, кому принадлежит: при
     * сохранении нового предмета, склада или хранилища клиент присылает её
     * вместе с остальным, и она привязывается. Если человек передумал и
     * закрыл форму, картинка остаётся ни с чем — такие убираются обслуживанием
     * отдельно.
     *
     * Владельца у картинки нет и не бывает: файлы общие и дедуплицируются по
     * содержимому, поэтому «загруженная здесь» и «та же самая, загруженная
     * раньше» — одна и та же строка. Привязать её к сущности вправе любой,
     * у кого есть права на эту сущность, — ровно как если бы он загрузил тот
     * же файл, уже зная её id.
     */
    public function storeUnattached(StoreImageRequest $request): JsonResponse
    {
        $image = Image::fromUploadedFile($request->file('file'));

        return (new ImageResource($image))->response()->setStatusCode(201);
    }

    public function storeForItem(StoreImageRequest $request, Item $model): JsonResponse
    {
        $this->requireEdit($model);

        // Запись картинки, привязка к предмету и журнал — одна транзакция.
        // Обрыв посередине оставил бы в базе картинку без привязки, а в
        // журнале — запись о действии, которого не было.
        [$image, $duplicates] = DB::transaction(function () use ($request, $model): array {
            [$image, $duplicates] = $this->store($request, $model);

            // Повтор не пишем в журнал: ничего не изменилось, а запись
            // «фото добавлено» была бы враньём. Уборка дублей тоже молчит —
            // это след прежнего поведения, а не действие человека.
            if ($duplicates === null) {
                $this->logImage('item', AuditAction::ImageAttached, $model, $image);
            }

            return [$image, $duplicates];
        });

        return $this->uploadResponse($image, $duplicates);
    }

    public function storeForStore(StoreImageRequest $request, Store $model): JsonResponse
    {
        $this->requireEdit($model);

        // Запись картинки, привязка к предмету и журнал — одна транзакция.
        // Обрыв посередине оставил бы в базе картинку без привязки, а в
        // журнале — запись о действии, которого не было.
        [$image, $duplicates] = DB::transaction(function () use ($request, $model): array {
            [$image, $duplicates] = $this->store($request, $model);

            // Повтор не пишем в журнал: ничего не изменилось, а запись
            // «фото добавлено» была бы враньём. Уборка дублей тоже молчит —
            // это след прежнего поведения, а не действие человека.
            if ($duplicates === null) {
                $this->logImage('store', AuditAction::ImageAttached, $model, $image);
            }

            return [$image, $duplicates];
        });

        return $this->uploadResponse($image, $duplicates);
    }

    public function storeForWarehouse(StoreImageRequest $request, Warehouse $model): JsonResponse
    {
        $this->requireEdit($model);

        // Запись картинки, привязка к предмету и журнал — одна транзакция.
        // Обрыв посередине оставил бы в базе картинку без привязки, а в
        // журнале — запись о действии, которого не было.
        [$image, $duplicates] = DB::transaction(function () use ($request, $model): array {
            [$image, $duplicates] = $this->store($request, $model);

            // Повтор не пишем в журнал: ничего не изменилось, а запись
            // «фото добавлено» была бы враньём. Уборка дублей тоже молчит —
            // это след прежнего поведения, а не действие человека.
            if ($duplicates === null) {
                $this->logImage('warehouse', AuditAction::ImageAttached, $model, $image);
            }

            return [$image, $duplicates];
        });

        return $this->uploadResponse($image, $duplicates);
    }

    /**
     * Привязка картинки к сущности и ответ на неё.
     *
     * Тело осталось прежним (data с картинкой), добавлены два признака:
     * attached — создана ли новая привязка, и duplicates_removed — сколько
     * повторов убрано. Клиенту нужно сказать человеку «эта картинка уже была
     * в списке», иначе повтор выглядит как тишина, в которой не разберёшься —
     * то ли загрузка не удалась, то ли дубликат.
     *
     * Одного счётчика не хватило бы: когда картинка привязана была ровно
     * один раз, убирать нечего, счётчик был бы нулём — тем же, что и у
     * успешной загрузки, и клиент добавил бы дубль в списке у себя.
     */
    private function uploadResponse(?Image $image, ?int $duplicates): JsonResponse
    {
        return response()->json([
            'data'               => $image === null ? null : (new ImageResource($image))->resolve(),
            'attached'           => $duplicates === null,
            'duplicates_removed' => $duplicates ?? 0,
        ], $duplicates === null ? 201 : 200);
    }

    /**
     * Загружает файл и привязывает его к сущности ровно один раз.
     *
     * Возвращает картинку и null, если привязка создана, либо число убранных
     * дублей, если картинка у сущности уже была.
     *
     * Картинки складываются в каталог по sha256, поэтому один и тот же файл,
     * загруженный повторно, приходит как та же самая строка image. Повторная
     * привязка создала бы вторую строку в image_m2m_* — и в списке фото одна
     * картинка стояла бы дважды, а счётчик показывал бы неправду. С мульти-
     * выбором файлов такое повторение становится обычным делом: один и тот же
     * файл кладут в пачку дважды или повторяют неудачную загрузку.
     *
     * Остаётся самая ранняя строка привязки: у неё уже есть alt и вес, а
     * выбрасывать её в пользу новой значило бы терять подпись, которую
     * человек старательно вписал.
     */
    private function store(StoreImageRequest $request, ImageOwner $model): array
    {
        $image = Image::fromUploadedFile($request->file('file'));

        [$pivot, $key] = $this->attach->pivotFor($model);

        $existing = DB::table($pivot)
            ->where($key, $model->id)
            ->where('image_id', $image->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($existing !== []) {
            $removed = DB::table($pivot)->whereIn('id', array_slice($existing, 1))->delete();

            return [$model->images()->where('image.id', $image->id)->first(), $removed];
        }

        $model->images()->attach($image->id, [
            'alt'    => null,
            'weight' => $this->attach->nextWeight($model),
        ]);

        return [$model->images()->where('image.id', $image->id)->first(), null];
    }

    public function updateAltForItem(UpdateImageAltRequest $request, Item $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        DB::transaction(function () use ($model, $image, $request): void {
            $model->images()->updateExistingPivot($image->id, ['alt' => $request->input('alt')]);
            $this->logImage('item', AuditAction::ImageAltUpdated, $model, $image, [
                'alt' => $request->input('alt'),
            ]);
        });

        return (new ImageResource($this->withPivot($model, $image)))->response();
    }

    public function updateAltForStore(UpdateImageAltRequest $request, Store $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        DB::transaction(function () use ($model, $image, $request): void {
            $model->images()->updateExistingPivot($image->id, ['alt' => $request->input('alt')]);
            $this->logImage('store', AuditAction::ImageAltUpdated, $model, $image, [
                'alt' => $request->input('alt'),
            ]);
        });

        return (new ImageResource($this->withPivot($model, $image)))->response();
    }

    public function reorderForItem(ReorderImagesRequest $request, Item $model): JsonResponse
    {
        $this->requireEdit($model);
        DB::transaction(function () use ($model, $request): void {
            $this->reorder($model, $request->validated('ids'));
            $this->logImage('item', AuditAction::ImageReordered, $model, null, [
                'ids' => $request->validated('ids'),
            ]);
        });

        return response()->json(['ids' => $request->validated('ids')]);
    }

    public function reorderForStore(ReorderImagesRequest $request, Store $model): JsonResponse
    {
        $this->requireEdit($model);
        DB::transaction(function () use ($model, $request): void {
            $this->reorder($model, $request->validated('ids'));
            $this->logImage('store', AuditAction::ImageReordered, $model, null, [
                'ids' => $request->validated('ids'),
            ]);
        });

        return response()->json(['ids' => $request->validated('ids')]);
    }

    public function reorderForWarehouse(ReorderImagesRequest $request, Warehouse $model): JsonResponse
    {
        $this->requireEdit($model);
        DB::transaction(function () use ($model, $request): void {
            $this->reorder($model, $request->validated('ids'));
            $this->logImage('warehouse', AuditAction::ImageReordered, $model, null, [
                'ids' => $request->validated('ids'),
            ]);
        });

        return response()->json(['ids' => $request->validated('ids')]);
    }

    public function updateAltForWarehouse(UpdateImageAltRequest $request, Warehouse $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        DB::transaction(function () use ($model, $image, $request): void {
            $model->images()->updateExistingPivot($image->id, ['alt' => $request->input('alt')]);
            $this->logImage('warehouse', AuditAction::ImageAltUpdated, $model, $image, [
                'alt' => $request->input('alt'),
            ]);
        });

        return (new ImageResource($this->withPivot($model, $image)))->response();
    }

    public function removeForWarehouse(Warehouse $model, Image $image): JsonResponse
    {
        $this->requireEdit($model);

        DB::transaction(function () use ($model, $image): void {
            $model->images()->detach($image->id);
            $this->logImage('warehouse', AuditAction::ImageDetached, $model, $image);
        });

        return response()->json(null, 204);
    }

    private function reorder(ImageOwner $model, array $ids): void
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

    private function withPivot(ImageOwner $model, Image $image): Image
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

    private function requireEdit(ImageOwner $model): void
    {
        abort_unless(app(AccessService::class)->canEdit($model), 403);
    }

    private function logImage(
        string $kind,
        AuditAction $action,
        ImageOwner $model,
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
            $kind . '_id',
            (int) $model->id,
            (int) ($model->user_id ?? 0),
            $payload,
        );
    }
}