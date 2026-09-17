<?php

namespace App\Models;

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
    ];

    public static function fromUploadedFile(UploadedFile $file): self
    {
        $sha256 = hash_file('sha256', $file->getRealPath());

        $existing = self::query()->where('sha256', $sha256)->first();

        if ($existing !== null) {
            return $existing;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) Str::after($file->getMimeType(), '/'));
        }
        $path = 'src/' . $sha256 . '.' . $extension;

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        try {
            return self::create([
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'sha256'        => $sha256,
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
