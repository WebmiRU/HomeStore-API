<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'image';

    protected $fillable = [];

    public function labelPresets()
    {
        return $this->belongsToMany(LabelPreset::class, 'image_m2m_label_preset')
            ->withTimestamps();
    }
}
