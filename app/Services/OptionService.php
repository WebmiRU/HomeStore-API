<?php

namespace App\Services;

use App\Models\Option;
use App\Support\CurrentUser;

/**
 * Настройки пользователя: чтение с умолчаниями и запись.
 *
 * Значения по умолчанию живут здесь, а не в базе. У пользователя, который
 * ничего не сохранял, строки в option нет вообще, и база не знает, что
 * показывать: GET обязан отдать полный набор, иначе фронт додумывает дефолты
 * у себя — и со временем фронт и база разойдутся.
 *
 * Ключи меню проверяются только на вид (список строк без повторов). Какие
 * ключи бывают, знает меню приложения, а не сервер: сервер не должен знать
 * состав пунктов, иначе добавление пункта потребует правки здесь. Ключ,
 * которого в меню больше нет, клиент просто игнорирует, а новый пункт,
 * которого в сохранённом порядке нет, дописывается в конец.
 */
class OptionService
{
    /**
     * Настройки текущего пользователя вместе с умолчаниями.
     *
     * Строка достаётся по user_id явно, а не через глобальный scope: ответ
     * всегда про «мои» настройки, и подменять это нечем.
     */
    public function forCurrentUser(): array
    {
        $userId = CurrentUser::id();

        if ($userId === null) {
            return $this->defaults();
        }

        $option = Option::query()->where('user_id', $userId)->first();

        return $option === null
            ? $this->defaults()
            : $this->present($option);
    }

    /**
     * Сохраняет настройки текущего пользователя.
     *
     * Строка создаётся лениво, при первом сохранении: заводить её при
     * создании пользователя означало бы писать дефолты в базу, которые и так
     * описаны в коде и всё равно меняются вместе с ним.
     *
     * @param  array  $data  прошедшие валидацию значения
     */
    public function saveForCurrentUser(array $data): array
    {
        $userId = CurrentUser::id();

        // Маршрут стоит за авторизацией, так что null здесь означает не
        // «пользователь без настроек», а программную ошибку: писать строку с
        // пустым user_id нельзя (колонка NOT NULL), и падать на этом в базе
        // нечего хорошего.
        abort_if($userId === null, 403, __('Нет текущего пользователя'));

        // Основа слияния — текущие значения, а не умолчания: правила запроса
        // помечают поля как sometimes, то есть запрос может прийти частичным.
        // При основании из умолчаний частичное сохранение молча возвращало бы
        // остальные настройки к исходным: поменял язык — потерял порядок меню.
        $current = $this->forCurrentUser();

        $option = Option::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'menu_order'      => $this->keyList($data['menu_order'] ?? $current['menu_order']),
                'menu_hidden'     => $this->keyList($data['menu_hidden'] ?? $current['menu_hidden']),
                'operation_mode'  => $data['operation_mode'] ?? $current['operation_mode'],
                'locale'          => in_array($data['locale'] ?? null, Option::locales(), true)
                    ? $data['locale']
                    : $current['locale'],
                'show_code_block' => array_key_exists('show_code_block', $data)
                    ? (bool) $data['show_code_block']
                    : $current['show_code_block'],
                'remember_operation_mode' => array_key_exists('remember_operation_mode', $data)
                    ? (bool) $data['remember_operation_mode']
                    : $current['remember_operation_mode'],
                // Тема и акцент проверяются здесь, а не только правилами
                // запроса: сюда попадает и то, что лежит в базе после правки
                // руками.
                'theme'          => in_array($data['theme'] ?? null, Option::themes(), true)
                    ? $data['theme']
                    : $current['theme'],
                'accent'         => in_array($data['accent'] ?? null, Option::accents(), true)
                    ? $data['accent']
                    : $current['accent'],
            ]
        );

        return $this->present($option);
    }

    /**
     * Что показывать пользователю, у которого строки настроек нет.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'menu_order'      => [],
            'menu_hidden'     => [],
            'operation_mode'  => Option::MODE_SEARCH,
            'locale'          => Option::LOCALE_RU,
            'show_code_block' => true,
            'remember_operation_mode' => true,
            'theme'          => Option::THEME_SYSTEM,
            'accent'         => Option::ACCENT_BLUE,
        ];
    }

    /**
     * Настройки строки в том же виде, что и умолчания.
     *
     * @return array<string, mixed>
     */
    private function present(Option $option): array
    {
        return [
            'menu_order'      => $this->keyList($option->menu_order ?? []),
            'menu_hidden'     => $this->keyList($option->menu_hidden ?? []),
            'operation_mode'  => $option->operation_mode ?: Option::MODE_SEARCH,
            'locale'          => in_array($option->locale, Option::locales(), true)
                ? $option->locale
                : Option::LOCALE_RU,
            'show_code_block' => (bool) $option->show_code_block,
            'remember_operation_mode' => (bool) $option->remember_operation_mode,
            'theme'          => in_array($option->theme, Option::themes(), true)
                ? $option->theme
                : Option::THEME_DARK,
            'accent'         => in_array($option->accent, Option::accents(), true)
                ? $option->accent
                : Option::ACCENT_GREEN,
        ];
    }

    /**
     * Приводит список ключей к виду, который ждёт клиент: строки без пустых.
     *
     * Повторы и пустые строки убираются здесь, а не только в правилах
     * запроса: правила проверяют присланное, а сюда попадает и то, что лежит
     * в базе после правки руками.
     *
     * @param  array<int, mixed>  $keys
     * @return array<int, string>
     */
    private function keyList(array $keys): array
    {
        $clean = [];

        foreach ($keys as $key) {
            if (! is_string($key)) {
                continue;
            }

            $key = trim($key);

            if ($key === '' || in_array($key, $clean, true)) {
                continue;
            }

            $clean[] = $key;
        }

        return $clean;
    }
}
