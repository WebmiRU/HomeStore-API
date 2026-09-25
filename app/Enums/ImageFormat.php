<?php

namespace App\Enums;

enum ImageFormat: string
{
    case Webp = 'webp';
    case Avif = 'avif';
    case Jpeg = 'jpeg';

    public function mime(): string
    {
        return match ($this) {
            self::Webp => 'image/webp',
            self::Avif => 'image/avif',
            self::Jpeg => 'image/jpeg',
        };
    }
}
