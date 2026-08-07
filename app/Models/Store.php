<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use Searchable;

    protected $table = 'store';

    protected $fillable = [
        'title',
        'title_print',
        'parent_id',
    ];

    public function parent()
    {
        return $this->belongsTo(Store::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Store::class, 'parent_id');
    }

    public function code()
    {
        return $this->hasOne(Code::class);
    }

    public function ancestors(): array
    {
        $chain = [];
        $current = $this->parent;

        while ($current) {
            $chain[] = $current->getAttributes();
            $current = $current->parent;
        }

        return array_reverse($chain);
    }
}
