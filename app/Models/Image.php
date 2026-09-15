<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    protected $table = 'image';

    protected $fillable = [
        'path',
        'original_name',
        'mime',
    ];

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
        return $this->belongsToMany(Store::class, 'image_m2m_sotre')
            ->withTimestamps();
    }

    public function url(): string
    {
        return Storage::disk('s3')->url($this->path);
    }

    public static function booted(): void
    {
        static::deleting(function (Image $image) {
            Storage::disk('s3')->delete($image->path);
        });
    }
}