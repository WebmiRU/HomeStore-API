<?php

use App\Enums\ImageCrop;
use App\Enums\ImageFormat;
use App\Models\Thumbnail;
use Illuminate\Database\Migrations\Migration;

/**
 * Миниатюра 800x800 с вписыванием — кадр для просмотра фотографии.
 *
 * Крупного размера в каталоге не было, а крупный кадр нужен: клик по фото в
 * индексной таблице открывает просмотр, и тянуть туда оригинал нельзя —
 * фотография товара весит сотни килобайт, тогда как вписывание в 800px
 * оставляет качество, достаточное для просмотра.
 *
 * Строка заводится миграцией, а не только сидером: каталог нужен в проде,
 * а сидер там никто не запускает.
 */
return new class extends Migration
{
    public function up(): void
    {
        Thumbnail::updateOrCreate(
            ['key' => '800x800_contain'],
            [
                'format' => ImageFormat::Avif,
                'width'  => 800,
                'height' => 800,
                'crop'   => ImageCrop::Contain,
            ]
        );
    }

    public function down(): void
    {
        Thumbnail::query()->where('key', '800x800_contain')->delete();
    }
};
