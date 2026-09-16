<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserToken extends Model
{
    protected $table = 'user_token';

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'expires_at',
        'last_used_at',
        'last_used_ip',
        'user_agent',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities'    => 'array',
            'expires_at'   => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}