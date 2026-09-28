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

    /**
     * Отдаёт миниатюру, делая её, если её ещё нет.
     *
     * Миниатюра кладётся в хранилище и с тех пор служит из него же: повторный
     * запрос — это одно чтение готового файла, а не скачивание оригинала,
     * ужимание и запись обратно. На список предметов это с десятью картинками
     * решает всё: раньше страница делала три обращения к хранилищу на каждую
     * картинку при каждом открытии, и список упирался в их общее число.
     *
     * Оригинал при этом не трогаем вовсе, а не только при удачной генерации:
     * если сеть моргнёт и оригинал не скачается, готовая миниатюра всё равно
     * отдастся, потому что до неё дело не доходит.
     */
    public function make(string $sourcePath, string $sha256, Thumbnail $thumbnail): string
    {
        $ready = Storage::disk('s3')->get($this->path($sha256, $thumbnail));

        if ($ready !== null) {
            return $ready;
        }

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

    /**
     * Вписывает или обрезает кадр под коробку width×height.
     *
     * Главное здесь — не увеличивать. Из снимка 473×162 нельзя получить
     * 800×800: растянутая копия качеством не лучше, а весит в разы больше, и
     * браузер получает «картинку», которой не существует. Если коробка больше
     * оригинала, отдаём то, что есть: при вписывании — сам оригинал, при
     * обрезке — квадрат по его меньшей стороне.
     *
     * Размер результата поэтому может оказаться меньше запрошенного: ключ
     * миниатюры называет коробку, а не файл. Клиент знает настоящие размеры
     * оригинала и объявляет в srcset ширину именно файла.
     */
    private function fit(GdImage $source, int $width, int $height, ImageCrop $crop): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $scale = $crop === ImageCrop::Cover
            // Обрезка идёт по большей из двух сторон: кадр должен накрыть коробку
            // целиком, лишнее срежется.
            ? min(1.0, max($width / $sourceWidth, $height / $sourceHeight))
            // Вписывание — по меньшей: картинка целиком внутри коробки.
            : min(1.0, min($width / $sourceWidth, $height / $sourceHeight));

        $scaled = $this->resize(
            $source,
            max(1, (int) round($sourceWidth * $scale)),
            max(1, (int) round($sourceHeight * $scale)),
        );

        if ($crop === ImageCrop::Contain) {
            return $scaled;
        }

        // Холст не может быть больше получившейся картинки: иначе imagecopy
        // вылезет за края и по ним будет прозрачная пустота.
        $canvasWidth = min($width, imagesx($scaled));
        $canvasHeight = min($height, imagesy($scaled));
        $canvas = $this->canvas($canvasWidth, $canvasHeight);

        imagecopy(
            $canvas,
            $scaled,
            0,
            0,
            (int) floor((imagesx($scaled) - $canvasWidth) / 2),
            (int) floor((imagesy($scaled) - $canvasHeight) / 2),
            $canvasWidth,
            $canvasHeight,
        );
        imagedestroy($scaled);

        return $canvas;
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
