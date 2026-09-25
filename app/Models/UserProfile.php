<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class UserProfile extends Model
{
    protected $table = 'user';

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_id',
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

    public function avatarImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'avatar_id');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatarImage?->url();
    }

    public function avatarSha(): ?string
    {
        return $this->avatarImage?->sha256;
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
