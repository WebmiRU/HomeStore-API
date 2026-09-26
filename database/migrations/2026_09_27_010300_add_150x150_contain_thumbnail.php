<?php

use App\Enums\ImageCrop;
use App\Enums\ImageFormat;
use App\Models\Thumbnail;
use Illuminate\Database\Migrations\Migration;

/**
 * Миниатюра 150x150 с вписыванием, а не обрезкой.
 *
 * Нужна для логотипа производителя в карточке: карточка показывает его
 * стороной 120px, а ключа такого размера в каталоге не было. Без него
 * запрос миниатюры отдавал 404 (ThumbnailController на неизвестный ключ
 * отвечает 404), и клиент брал оригинал целиком.
 *
 * Строка заводится миграцией, а не только сидером: каталог миниатюр
 * нужен в проде, а сидер там никто не запускает.
 */
return new class extends Migration
{
    public function up(): void
    {
        Thumbnail::updateOrCreate(
            ['key' => '150x150_contain'],
            [
                'format' => ImageFormat::Avif,
                'width'  => 150,
                'height' => 150,
                'crop'   => ImageCrop::Contain,
            ]
        );
    }

    public function down(): void
    {
        Thumbnail::query()->where('key', '150x150_contain')->delete();
    }
};
