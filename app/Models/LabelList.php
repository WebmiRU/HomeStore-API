<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelList extends Model
{

    protected $table = 'label_list';

    protected $fillable = [
        'title',
        'label_preset_id',
    ];

    public function labelPreset()
    {
        return $this->belongsTo(LabelPreset::class);
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'label_list_m2m_item')
            ->withTimestamps();
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'label_list_m2m_store')
            ->withTimestamps();
    }
}
