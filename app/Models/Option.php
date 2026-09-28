<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Настройки пользователя. Строка одна на человека.
 *
 * Мягкого удаления нет намеренно: настройки не удаляют, их возвращают к
 * умолчаниям, и это та же строка с теми же дефолтами.
 */
class Option extends Model
{
    use OwnedByUser;

    protected $table = 'option';

    protected $fillable = [
        'user_id',
        'menu_order',
        'menu_hidden',
        'operation_mode',
        'locale',
        'show_code_block',
        'remember_operation_mode',
    ];

    protected $casts = [
        'menu_order'      => 'array',
        'menu_hidden'     => 'array',
        'show_code_block' => 'boolean',
        'remember_operation_mode' => 'boolean',
    ];

    /** Режимы работы на странице предметов. */
    public const MODE_SEARCH = 'search';

    public const MODE_REPLENISH = 'replenish';

    public const MODE_WRITEOFF = 'writeoff';

    /** Языки интерфейса и сообщений. */
    public const LOCALE_RU = 'ru';

    public const LOCALE_EN = 'en';

    /** @return array<int, string> */
    public static function locales(): array
    {
        return [self::LOCALE_RU, self::LOCALE_EN];
    }

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }
}
