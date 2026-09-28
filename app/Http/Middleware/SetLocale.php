<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Язык ответов API — по заголовку Accept-Language.
 *
 * Русский и английский. Русский — источник правды: строки API хранятся в коде
 * по-русски, и именно они являются ключами перевода (см. lang/en.json).
 * Файл ru.json не нужен и не заводится: без перевода ключ отдаётся сам себе
 * и получается русский текст. Так же работает запасной язык — 'ru' в
 * config/app.php.
 *
 * Язык приходит заголовком, а не полем у пользователя: сообщение об ошибке
 * надо показать на том же языке, на котором человек читает интерфейс, и он
 * мог переключить язык, ещё не сохранив настройку.
 */
class SetLocale
{
    /** Языки, которые сервер знает. */
    private const SUPPORTED = ['ru', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request->header('Accept-Language')));

        return $next($request);
    }

    /**
     * Язык из заголовка Accept-Language.
     *
     * Берётся первый тег: «en-US,en;q=0.9» — это en, а не «en-US» как
     * отдельный язык. Чего в заголовке нет или что сервер не знает, берётся
     * русский: интерфейс на русском — основной, и молчаливый откат на
     * английский в нём был бы хуже явного русского.
     */
    private function resolve(?string $header): string
    {
        if ($header === null || $header === '') {
            return 'ru';
        }

        $first = explode(',', $header)[0];
        $tag = strtolower(trim(explode(';', $first)[0]));
        $tag = explode('-', $tag)[0];

        return in_array($tag, self::SUPPORTED, true) ? $tag : 'ru';
    }
}
