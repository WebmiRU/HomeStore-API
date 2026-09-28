<?php

namespace App\Models;

use App\Enums\ImageCrop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Image extends Model
{
    protected $table = 'image';

    protected $fillable = [
        'path',
        'original_name',
        'mime',
        'sha256',
        'width',
        'height',
        'size',
    ];

    protected $casts = [
        'width'  => 'integer',
        'height' => 'integer',
        'size'   => 'integer',
    ];

    /**
     * Наибольшая сторона миниатюры, которую ещё можно сделать из этой картинки.
     *
     * Обрезка по квадрату режет по меньшей стороне: квадрат 160×160 из снимка
     * 473×162 получится (162 ≥ 160), а из 90×60 — нет, пришлось бы растянуть,
     * а качество от растягивания не лучше. Вписывание уменьшает по большей
     * стороне, и там предел — она сама.
     *
     * null, когда размеров оригинала нет: тогда клиент решает по каталогу, как
     * и раньше.
     */
    public function thumbLimit(ImageCrop $crop): ?int
    {
        if ($this->width === null || $this->height === null) {
            return null;
        }

        return $crop === ImageCrop::Cover
            ? (int) min($this->width, $this->height)
            : (int) max($this->width, $this->height);
    }

    /** Пределы по обеим обрезкам: что вообще можно получить из картинки. */
    public function thumbLimits(): ?array
    {
        $cover = $this->thumbLimit(ImageCrop::Cover);
        $contain = $this->thumbLimit(ImageCrop::Contain);

        return $cover === null ? null : ['cover' => $cover, 'contain' => $contain];
    }

    public static function fromUploadedFile(UploadedFile $file): self
    {
        $sha256 = hash_file('sha256', $file->getRealPath());
        $size = (int) filesize($file->getRealPath());
        $dimensions = @getimagesize($file->getRealPath());

        $existing = self::query()->where('sha256', $sha256)->first();

        if ($existing !== null) {
            return $existing;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) Str::after($file->getMimeType(), '/'));
        }
        $path = 'images/src/' . $sha256 . '.' . $extension;

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        // Размеры оригинала: клиент по ним режет srcset, чтобы не предлагать
        // браузеру вариант больше, чем в картинке есть. null, если файл не
        // распознан как картинка, — тогда клиент просто возьмёт что есть.
        $width = $dimensions === false ? null : $dimensions[0];
        $height = $dimensions === false ? null : $dimensions[1];

        try {
            return self::create([
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'sha256'        => $sha256,
                'width'         => $width,
                'height'        => $height,
                'size'          => $size,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }

            return self::query()->where('sha256', $sha256)->firstOrFail();
        }
    }

    public function labelPresets()
    {
        return $this->belongsToMany(LabelPreset::class, 'image_m2m_label_preset')
            ->withTimestamps();
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'image_m2m_item')
            ->withTimestamps();
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'image_m2m_store')
            ->withTimestamps();
    }

    public function url(): string
    {
        return Storage::disk('s3')->url($this->path);
    }
}
