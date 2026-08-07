<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'item';

    protected $fillable = [
        'title',
        'title_print',
        'store_id',
    ];

    public function code()
    {
        return $this->hasOne(Code::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
