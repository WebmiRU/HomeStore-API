<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    protected $table = 'user';

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    protected $hidden = [
        'password',
    ];

    public function setPasswordAttribute(?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    public function verifyPassword(string $plain): bool
    {
        return $this->password !== null && Hash::check($plain, $this->password);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar !== null ? Storage::disk('s3')->url($this->avatar) : null;
    }

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