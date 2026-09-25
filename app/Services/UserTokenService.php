<?php

namespace App\Services;

use App\Models\UserProfile;
use App\Models\UserToken;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class UserTokenService
{
    /**
     * Объём случайных данных для генерации токена.
     * Из них берётся sha512-хеш — это и есть значение токена.
     */
    public const ENTROPY_BYTES = 1024;

    /**
     * Создаёт токен (sha512 от >= 1 КБ крипто-стойких случайных данных)
     * и сохраняет его в таблице user_token.
     *
     * @return string значение токена для передачи клиенту
     */
    public function issue(UserProfile $user, ?string $name = null, ?CarbonInterface $expiresAt = null, array $abilities = []): string
    {
        $token = Str::lower(hash('sha512', random_bytes(self::ENTROPY_BYTES)));

        UserToken::create([
            'user_id'    => $user->id,
            'name'       => $name,
            'token'      => $token,
            'abilities'  => $abilities === [] ? null : $abilities,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Возвращает неотозванный и не истёкший токен пользователя или null.
     */
    public function resolve(string $plain): ?UserToken
    {
        $token = UserToken::where('token', Str::lower($plain))->first();

        if (! $token || $token->revoked_at !== null) {
            return null;
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    public function revoke(UserToken $token): void
    {
        $token->update(['revoked_at' => now()]);
    }

    public function revokeAll(UserProfile $user): void
    {
        $user->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /**
     * Отмечает факт использования токена (например, из middleware авторизации).
     */
    public function touch(UserToken $token, ?string $ip = null, ?string $userAgent = null): void
    {
        $token->update(array_filter([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'user_agent'   => $userAgent !== null ? Str::limit($userAgent, 512) : null,
        ], fn ($value) => $value !== null));
    }
}