<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorLogoRequest;
use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Image;
use App\Models\Vendor;
use App\Services\ImageCleanupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VendorController extends Controller
{
    public function index(): ResourceCollection
    {
        return VendorResource::collection(
            Vendor::with(['logoImage', 'user'])->orderBy('title')->paginate()
        );
    }

    /**
     * Поставщики без пагинации: селект в форме предмета должен показать
     * всё, иначе нужный поставщикь оказывался за пределами первой
     * страницы.
     */
    public function all(): ResourceCollection
    {
        return VendorResource::collection(
            Vendor::with('logoImage')->orderBy('title')->get()
        );
    }

    public function get(Vendor $model): VendorResource
    {
        return new VendorResource($model->load(['logoImage', 'user']));
    }

    public function post(StoreVendorRequest $request): JsonResponse
    {
        $vendor = Vendor::create($request->validated());

        return (new VendorResource($vendor->load(['logoImage', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateVendorRequest $request, Vendor $model): VendorResource
    {
        $model->update($request->validated());

        return new VendorResource($model->load(['logoImage', 'user']));
    }

    /**
     * Загрузка логотипа. Идёт тем же путём, что и остальные картинки:
     * Image::fromUploadedFile() складывает файл в общий каталог по sha256,
     * поэтому один и тот же логотип, загруженный дважды, хранится один раз.
     *
     * POST /api/vendor/{model}/logo
     */
    public function storeLogo(
        StoreVendorLogoRequest $request,
        Vendor $model,
        ImageCleanupService $cleanup,
    ): VendorResource {
        $image = Image::fromUploadedFile($request->file('file'));

        $oldLogoId = $model->logo_id;

        $model->update(['logo_id' => $image->id]);

        // Замена логотипа не должна оставлять в каталоге файл, на который
        // уже никто не ссылается. Тот же sha256, что и у нового, не трогаем:
        // картинка остаётся в деле, и удаление её сломало бы новый логотип.
        if ($oldLogoId !== null && $oldLogoId !== $image->id) {
            $cleanup->deleteIfUnused($oldLogoId);
        }

        return new VendorResource($model->load(['logoImage', 'user']));
    }

    /**
     * Снятие логотипа. Сам поставщикь остаётся — пропадает только
     * картинка, и только если на неё больше никто не ссылается.
     *
     * DELETE /api/vendor/{model}/logo
     */
    public function deleteLogo(Vendor $model, ImageCleanupService $cleanup): JsonResponse
    {
        $oldLogoId = $model->logo_id;

        if ($oldLogoId !== null) {
            $model->update(['logo_id' => null]);

            $cleanup->deleteIfUnused($oldLogoId);
        }

        return response()->json(null, 204);
    }

    public function delete(Vendor $model, ImageCleanupService $cleanup): JsonResponse
    {
        $oldLogoId = $model->logo_id;

        $model->delete();

        // Логотип остался в каталоге изображений без ссылок — убираем,
        // чтобы он не копился вместе с удалёнными поставщикями.
        if ($oldLogoId !== null) {
            $cleanup->deleteIfUnused($oldLogoId);
        }

        return response()->json(null, 204);
    }
}
