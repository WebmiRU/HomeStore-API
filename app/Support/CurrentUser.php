<?php

namespace App\Support;

use App\Models\UserProfile;

/**
 * Текущий аутентифицированный пользователь в рамках запроса.
 * Заполняется middleware EnsureTokenAuth; в консоли/миграциях — null.
 */
class CurrentUser
{
    private static ?UserProfile $user = null;

    public static function set(?UserProfile $user): void
    {
        self::$user = $user;
    }

    public static function get(): ?UserProfile
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user?->id;
    }
}