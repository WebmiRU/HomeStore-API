<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\UserProfile;
use App\Models\Warehouse;
use App\Support\DefaultUnits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserRegistrationService
{
    public const DEFAULT_WAREHOUSE_TITLE = 'Домашний склад';

    /**
     * Регистрирует пользователя и создаёт сущности по умолчанию.
     *
     * Email обязателен. Всё выполняется в одной транзакции, поэтому при
     * любой ошибке пользователь не будет создан наполовину.
     *
     * @throws \InvalidArgumentException
     */
    public function register(array $data): UserProfile
    {
        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '') {
            throw new \InvalidArgumentException('Email is required for registration.');
        }

        $name = trim((string) ($data['name'] ?? '')) ?: Str::before($email, '@');

        return DB::transaction(function () use ($email, $name) {
            $user = UserProfile::create([
                'name'  => $name,
                'email' => $email,
            ]);

            $this->provisionDefaultEntities($user);

            return $user;
        });
    }

    /**
     * Создаёт сущности по умолчанию для нового пользователя.
     * Сюда удобно добавлять новые сущности в будущем.
     */
    protected function provisionDefaultEntities(UserProfile $user): void
    {
        Warehouse::create([
            'title'   => self::DEFAULT_WAREHOUSE_TITLE,
            'user_id' => $user->id,
        ]);

        foreach (DefaultUnits::definition() as $unit) {
            Unit::create($unit + ['user_id' => $user->id]);
        }
    }
}