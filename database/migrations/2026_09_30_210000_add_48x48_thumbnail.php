<?php

use App\Enums\ImageCrop;
use App\Enums\ImageFormat;
use App\Models\Thumbnail;
use Illuminate\Database\Migrations\Migration;

/**
 * Миниатюра 48x48 — под список предметов, складов и хранилищ.
 *
 * Миниатюры в этих списках стали крупнее (48px против прежних 40), а каталог
 * размеров заводится миграцией: без строки в thumbnail контроллер отдаёт 404,
 * и вместо картинки — заглушка. Ступени srcset берутся лестницей до удвоенного
 * CSS-размера, поэтому на ретине понадобится и 96x96.
 */
return new class extends Migration
{
    private const ADDED = [
        '48x48_cover' => [48, 48, ImageCrop::Cover],
        '96x96_cover' => [96, 96, ImageCrop::Cover],
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