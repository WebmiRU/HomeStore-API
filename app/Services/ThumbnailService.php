<?php

namespace App\Services;

use App\Enums\ImageCrop;
use App\Enums\ImageFormat;
use App\Models\Thumbnail;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ThumbnailService
{
    private const QUALITY = 90;

    public function make(string $sourcePath, string $sha256, Thumbnail $thumbnail): string
    {
        $source = Storage::disk('s3')->get($sourcePath);

        if ($source === null) {
            throw new RuntimeException('Оригинал не найден в хранилище');
        }

        $decoded = @imagecreatefromstring($source);

        if ($decoded === false) {
            throw new RuntimeException('Не удалось декодировать оригинал');
        }

        $canvas = $this->fit($decoded, $thumbnail->width, $thumbnail->height, $thumbnail->crop);
        imagedestroy($decoded);

        $bytes = $this->encode($canvas, $thumbnail->format);
        imagedestroy($canvas);

        Storage::disk('s3')->put($this->path($sha256, $thumbnail), $bytes, [
            'ContentType' => $thumbnail->format->mime(),
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $bytes;
    }

    public function path(string $sha256, Thumbnail $thumbnail): string
    {
        return "images/thumbnails/{$thumbnail->key}/{$sha256}";
    }

    private function fit(GdImage $source, int $width, int $height, ImageCrop $crop): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($crop === ImageCrop::Cover) {
            $scale = max($width / $sourceWidth, $height / $sourceHeight);
            $scaled = $this->resize($source, (int) ceil($sourceWidth * $scale), (int) ceil($sourceHeight * $scale));

            $canvas = $this->canvas($width, $height);
            imagecopy(
                $canvas,
                $scaled,
                0,
                0,
                (int) floor((imagesx($scaled) - $width) / 2),
                (int) floor((imagesy($scaled) - $height) / 2),
                $width,
                $height,
            );
            imagedestroy($scaled);

            return $canvas;
        }

        $scale = min($width / $sourceWidth, $height / $sourceHeight);

        return $this->resize(
            $source,
            max(1, (int) round($sourceWidth * $scale)),
            max(1, (int) round($sourceHeight * $scale)),
        );
    }

    private function resize(GdImage $source, int $width, int $height): GdImage
    {
        $canvas = $this->canvas($width, $height);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $canvas;
    }

    private function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }

    private function encode(GdImage $image, ImageFormat $format): string
    {
        ob_start();

        match ($format) {
            ImageFormat::Avif => imageavif($image, null, self::QUALITY),
            ImageFormat::Webp => imagewebp($image, null, self::QUALITY),
            ImageFormat::Jpeg => $this->encodeJpeg($image),
        };

        return (string) ob_get_clean();
    }

    private function encodeJpeg(GdImage $image): void
    {
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefilledrectangle($canvas, 0, 0, imagesx($image), imagesy($image), imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        imagejpeg($canvas, null, self::QUALITY);

        imagedestroy($canvas);
    }
}
