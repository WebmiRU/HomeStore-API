<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Font extends Model
{
    protected $table = 'font';

    protected $fillable = [
        'name',
        'key',
    ];

    public function labelPresets()
    {
        return $this->hasMany(LabelPreset::class);
    }
}
