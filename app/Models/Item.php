<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use Searchable;

    protected $table = 'item';

    protected $fillable = [
        'title',
        'title_print',
        'store_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function code()
    {
        return $this->hasOne(Code::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
