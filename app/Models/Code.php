<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;

use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    use OwnedByUser;

    protected $table = 'code';

    protected $fillable = [
        'code',
        'store_id',
        'item_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
