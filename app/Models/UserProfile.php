<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserProfile extends Model
{
    protected $table = 'user';

    protected $fillable = [
        'name',
        'email',
    ];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'user_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(UserToken::class, 'user_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(Code::class, 'user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'user_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'user_id');
    }

    public function labelLists(): HasMany
    {
        return $this->hasMany(LabelList::class, 'user_id');
    }

    public function labelPresets(): HasMany
    {
        return $this->hasMany(LabelPreset::class, 'user_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'user_id');
    }
}