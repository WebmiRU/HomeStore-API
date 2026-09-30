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
        'remember_operation_mode',
        'theme',
        'accent',
    ];

    protected $casts = [
        'menu_order'      => 'array',
        'menu_hidden'     => 'array',
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

    /** Тёмная тема: та, какой интерфейс был до появления переключателя. */
    public const THEME_DARK = 'dark';

    /*
     * Тема по умолчанию — «как в системе»: человек открывает интерфейс на
     * своём компьютере, и тёмный вид на светлом окружении выглядит хуже,
     * чем родной. Явный выбор перекрывает его, и настройка аккаунта
     * переезжает на другое устройство.
     */

    /** Светлая тема. */
    public const THEME_LIGHT = 'light';

    /**
     * Тема как в системе: берётся из prefers-color-scheme.
     *
     * Отдельным значением, а не пустым: по настройке видно, что человек
     * выбрал «как в системе», иначе пустое поле читалось бы как «не выбрано».
     */
    public const THEME_SYSTEM = 'system';

    /** @return array<int, string> */
    public static function themes(): array
    {
        return [self::THEME_DARK, self::THEME_LIGHT, self::THEME_SYSTEM];
    }

    /** Акцентный цвет: зелёный, тот, что был в интерфейсе до переключателя. */
    public const ACCENT_GREEN = 'green';

    /** Пурпурный. */
    public const ACCENT_PURPLE = 'purple';

    /** Синий. */
    public const ACCENT_BLUE = 'blue';

    /** Янтарный. */
    public const ACCENT_AMBER = 'amber';

    /**
     * Акцентные цвета.
     *
     * Список знает только фронт: он же рисует образцы цвета в настройках и
     * переключает тему в разметке. Серверу достаточно проверить, что значение
     * из списка, — какой оттенок означает какое слово, он не решает.
     *
     * @return array<int, string>
     */
    public static function accents(): array
    {
        return [self::ACCENT_GREEN, self::ACCENT_PURPLE, self::ACCENT_BLUE, self::ACCENT_AMBER];
    }

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }
}
