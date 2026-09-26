<?php

namespace App\Services;

use App\Models\Image;
use App\Models\UserProfile;
use App\Models\Vendor;
use Illuminate\Support\Facades\Storage;

/**
 * Удаление картинки, на которую больше никто не ссылается.
 *
 * Картинки складываются в общий каталог по sha256, поэтому один и тот же
 * файл может быть загружен и аватаром, и логотипом: удалять его можно
 * только когда не осталось ни одной ссылки — иначе пропала бы картинка
 * у сущности, которая её использует.
 */
class ImageCleanupService
{
    public function deleteIfUnused(int $imageId): void
    {
        $image = Image::query()->find($imageId);

        if ($image === null) {
            return;
        }

        if ($this->isUsed($image)) {
            return;
        }

        $path = $image->path;

        $image->delete();

        Storage::disk('s3')->delete($path);
    }

    private function isUsed(Image $image): bool
    {
        return $image->items()->exists()
            || $image->stores()->exists()
            || $image->labelPresets()->exists()
            || UserProfile::query()->where('avatar_id', $image->id)->exists()
            || Vendor::query()->where('logo_id', $image->id)->exists();
    }
}
