<?php

use App\Enums\ImageCrop;
use App\Enums\ImageFormat;
use App\Models\Thumbnail;
use Illuminate\Database\Migrations\Migration;

/**
 * Миниатюры под реальные размеры, в которых картинки показываются.
 *
 * До сих пор всё тянуло 100x100: в дереве содержимого миниатюра занимает
 * 22–28 пикселей, в списках 40, в поиске 72, аватар в шапке 56, логотип
 * производителя и аватар в карточке — 120. Миниатюра в четыре с половиной
 * раза больше места, чем она занимает, и в дереве это десятки лишних
 * килобайт на открытие вкладки.
 *
 * Размеры подобраны под retina: берётся наименьшая лестница, которая не
 * меньше удвоенного CSS-размера. 22 → 50, 28 → 60, 40 и 56 → 100,
 * 72 → 150, 84 → 200, 120 → 250, просмотр → 800 (он и был).
 *
 * Строка заводится миграцией, а не сидером: каталог нужен в проде, а сидер
 * там никто не запускает.
 */
return new class extends Migration
{
    /** Ключ => [ширина, высота, обрезка]. */
    private const ADDED = [
        '50x50_cover'    => [50, 50, ImageCrop::Cover],
        '60x60_cover'    => [60, 60, ImageCrop::Cover],
        '250x250_cover'  => [250, 250, ImageCrop::Cover],
        // Логотип производителя вписывается, а не обрезается: квадратная
        // обрезка срезала бы у логотипа края, ради которых он и логотип.
        '250x250_contain' => [250, 250, ImageCrop::Contain],
    ];

    public function up(): void
    {
        foreach (self::ADDED as $key => [$width, $height, $crop]) {
            Thumbnail::updateOrCreate(
                ['key' => $key],
                [
                    'format' => ImageFormat::Avif,
                    'width'  => $width,
                    'height' => $height,
                    'crop'   => $crop,
                ]
            );
        }
    }

    public function down(): void
    {
        Thumbnail::query()->whereIn('key', array_keys(self::ADDED))->delete();
    }
};
