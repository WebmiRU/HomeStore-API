<?php

namespace App\Support;

/**
 * Приведение введённого кода к вариантам, которыми он может храниться.
 *
 * Коды бывают двух записей одного UUID: с дефисами (36 символов) и без
 * (32 hex). Исторически в базе лежали дефисные, а коды из наборов
 * безымянных этикеток пишутся без дефисов — символ DataMatrix на этом
 * на два модуля меньше. Сканер отдаёт ровно то, что напечатано, поэтому
 * искать надо оба варианта независимо от того, как код отсканирован.
 */
final class CodeFormat
{
    /**
     * Варианты строки кода для поиска: исходный плюс обе записи того же
     * UUID — с дефисами и без. Нужны все три, потому что в базе лежат и
     * дефисные коды (исторические, предметов и хранилищ), и бездефисные
     * (из наборов безымянных этикеток), а просканировать можно любую
     * запись. Порядок значим: первое совпадение отдаётся клиенту как
     * «как отсканировали», остальные кандидаты отбрасываются.
     *
     * @return array<int, string>
     */
    public static function candidates(string $code): array
    {
        $code = trim($code);

        $bare = self::toBare($code);

        if ($bare === null) {
            return [$code];
        }

        $dashed = implode('-', [
            substr($bare, 0, 8),
            substr($bare, 8, 4),
            substr($bare, 12, 4),
            substr($bare, 16, 4),
            substr($bare, 20),
        ]);

        return array_values(array_unique([$code, $bare, $dashed]));
    }

    /**
     * UUID без дефисов (32 hex) из введённой строки, либо null, если ввод
     * не похож на UUID ни в одной из двух записей.
     */
    private static function toBare(string $code): ?string
    {
        if (strlen($code) === 32 && ctype_xdigit($code)) {
            return strtolower($code);
        }

        // Дефисная запись: 8-4-4-4-12, все символы — hex.
        if (preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/', $code) === 1) {
            return strtolower(str_replace('-', '', $code));
        }

        return null;
    }
}
