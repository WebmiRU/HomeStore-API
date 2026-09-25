<?php

namespace Database\Seeders;

use App\Enums\ImageCrop;
use App\Enums\ImageFormat;
use App\Models\Thumbnail;
use Illuminate\Database\Seeder;

class ThumbnailSeeder extends Seeder
{
    public function run(): void
    {
        Thumbnail::updateOrCreate(
            ['key' => '100x100_contain'],
            [
                'format' => ImageFormat::Avif,
                'width' => 100,
                'height' => 100,
                'crop' => ImageCrop::Contain,
            ]
        );

        Thumbnail::updateOrCreate(
            ['key' => '100x100_cover'],
            [
                'format' => ImageFormat::Avif,
                'width' => 100,
                'height' => 100,
                'crop' => ImageCrop::Cover,
            ]
        );

        Thumbnail::updateOrCreate(
            ['key' => '150x150_cover'],
            [
                'format' => ImageFormat::Avif,
                'width' => 150,
                'height' => 150,
                'crop' => ImageCrop::Cover,
            ]
        );

        Thumbnail::updateOrCreate(
            ['key' => '200x200_cover'],
            [
                'format' => ImageFormat::Avif,
                'width' => 200,
                'height' => 200,
                'crop' => ImageCrop::Cover,
            ]
        );

    }
}
