<?php

namespace Tests\Unit;

use App\Enums\ImageCrop;
use App\Models\Thumbnail;
use App\Services\ThumbnailService;
use App\Enums\ImageFormat;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Миниатюра никогда не больше оригинала.
 *
 * Растянутая копия качеством не лучше, а весит в разы больше: из снимка
 * 473×162 сервис раньше делал «800×800» — картинку 800×274, которой не
 * существует. Теперь ключ называет коробку, а файл всегда не больше того, из
 * чего сделан.
 *
 * Тест работает с настоящим GD и фальшивым хранилищем: проверять тут
 * нечего, кроме арифметики масштаба и того, что в файл попадает именно
 * получившаяся картинка, а не холст большего размера.
 */
class ThumbnailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_contain_never_enlarges(): void
    {
        $source = $this->sourceImage(473, 162);
        $bytes = $this->make($source, 800, 800, ImageCrop::Contain);

        [$width, $height] = $this->sizeOf($bytes);

        $this->assertSame(473, $width, 'Вписывание растянуло картинку по ширине');
        $this->assertSame(162, $height, 'Вписывание растянуло картинку по высоте');
    }

    public function test_contain_shrinks_to_fit_inside_the_box(): void
    {
        $source = $this->sourceImage(473, 162);
        $bytes = $this->make($source, 160, 160, ImageCrop::Contain);

        [$width, $height] = $this->sizeOf($bytes);

        $this->assertSame(160, $width);
        $this->assertSame(55, $height, 'Высота должна сохранить пропорции, а не стать квадратом');
        $this->assertLessThanOrEqual(160, $height, 'Картинка должна помещаться в коробку целиком');
    }

    public function test_cover_crops_a_square_without_enlarging(): void
    {
        $source = $this->sourceImage(473, 162);
        $bytes = $this->make($source, 84, 84, ImageCrop::Cover);

        [$width, $height] = $this->sizeOf($bytes);

        $this->assertSame(84, $width);
        $this->assertSame(84, $height);
    }

    public function test_cover_of_a_box_larger_than_the_source_is_clamped(): void
    {
        $source = $this->sourceImage(473, 162);
        $bytes = $this->make($source, 240, 240, ImageCrop::Cover);

        [$width, $height] = $this->sizeOf($bytes);

        // По короткой стороне доступно только 162: холст 240×240 означал бы
        // растянутые края, поэтому он сжимается до настоящего размера.
        $this->assertSame(240, $width);
        $this->assertSame(162, $height);
    }

    public function test_result_is_never_larger_than_the_source_in_any_axis(): void
    {
        foreach ([[473, 162], [90, 60], [120, 120], [1000, 40]] as [$width, $height]) {
            foreach ([ImageCrop::Cover, ImageCrop::Contain] as $crop) {
                foreach ([38, 84, 800] as $side) {
                    $bytes = $this->make($this->sourceImage($width, $height), $side, $side, $crop);
                    [$madeWidth, $madeHeight] = $this->sizeOf($bytes);

                    $this->assertLessThanOrEqual($width, $madeWidth, "{$width}×{$height} {$crop->value} {$side}: ширина выросла");
                    $this->assertLessThanOrEqual($height, $madeHeight, "{$width}×{$height} {$crop->value} {$side}: высота выросла");
                }
            }
        }
    }

    /**
     * Кладёт картинку в поддельное хранилище и просит сервис сделать миниатюру.
     */
    private function make(\GdImage $source, int $width, int $height, ImageCrop $crop): string
    {
        ob_start();
        imagepng($source);
        $original = (string) ob_get_clean();
        imagedestroy($source);

        $sha = hash('sha256', $original . $width . $height . $crop->value);
        Storage::disk('s3')->put("images/src/{$sha}", $original);

        $thumbnail = new Thumbnail([
            'format' => ImageFormat::Avif,
            'width'  => $width,
            'height' => $height,
            'crop'   => $crop,
            'key'    => "{$width}x{$height}_{$crop->value}",
        ]);

        return app(ThumbnailService::class)->make("images/src/{$sha}", $sha, $thumbnail);
    }

    /** Разноцветная картинка заданного размера: по ней видна каждая сторона. */
    private function sourceImage(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 200));
        imagefilledrectangle(
            $image,
            0,
            0,
            max(1, (int) ($width / 2)),
            max(1, (int) ($height / 2)),
            imagecolorallocate($image, 220, 80, 60),
        );

        return $image;
    }

    /** @return array{0: int, 1: int} */
    private function sizeOf(string $bytes): array
    {
        $image = imagecreatefromstring($bytes);
        $this->assertNotFalse($image, 'Миниатюра не декодируется');

        $size = [imagesx($image), imagesy($image)];
        imagedestroy($image);

        return $size;
    }
}
