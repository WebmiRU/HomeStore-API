<?php

namespace App\Models;

use App\Enums\ImageCrop;
use App\Enums\ImageFormat;
use Illuminate\Database\Eloquent\Model;

class Thumbnail extends Model
{
    protected $table = 'thumbnail';

    protected $fillable = [
        'format',
        'width',
        'height',
        'crop',
        'key',
    ];

    protected $casts = [
        'format' => ImageFormat::class,
        'crop' => ImageCrop::class,
        'width' => 'integer',
        'height' => 'integer',
    ];
}
