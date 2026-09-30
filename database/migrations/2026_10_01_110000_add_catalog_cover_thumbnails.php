<?php

use App\Enums\ImageCrop;
use App\Enums\ImageFormat;
use App\Models\Thumbnail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Миниатюры 200, 300 и 400 для «Каталога».
 *
 * Обложка плитки — 200 пикселей, и лестница srcset берёт размеры от неё: 200
 * для обычного экрана, 300 на 1.5× и 400 на ретине. Ближайшие ступени, какие
 * были в каталоге, — 180 и 240: для обложки 200×200 браузер брал бы 240 и
 * показывал вдвое больше, чем нужно, а на ретине не брал ничего вовсе и
 * растягивал 240 на 400.
 */
return new class extends Migration
{
    private const ADDED = [
        '200x200_cover' => [200, 200],
        '300x300_cover' => [300, 300],
        '400x400_cover' => [400, 400],
    ];

    public function up(): void
    {
        foreach (self::ADDED as $key => [$width, $height]) {
            Thumbnail::updateOrCreate(
                ['key' => $key],
                [
                    'format' => ImageFormat::Avif,
                    'width'  => $width,
                    'height' => $height,
                    'crop'   => ImageCrop::Cover,
                ]
            );
        }
    }

    public function down(): void
    {
        Thumbnail::query()->whereIn('key', array_keys(self::ADDED))->delete();
    }
};