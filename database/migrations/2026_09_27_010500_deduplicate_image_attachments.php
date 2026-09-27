<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Убирает повторные привязки одной картинки к одной сущности.
 *
 * Загрузка складывает картинки в каталог по sha256, поэтому повторная
 * загрузка того же файла привязывала его вторым числом: в image_m2m_*
 * нет ограничения на пару (сущность, картинка). В списке фото такая
 * картинка стояла дважды, и счётчик показывал неправду.
 *
 * Теперь повтор убирает дубли на лету (ImageController), а эта миграция
 * приводит в порядок то, что накопилось раньше.
 *
 * Остаётся самая ранняя строка: у неё уже есть alt и weight, а выбрасывать
 * её в пользу более поздней значило бы терять вписанную подпись.
 */
return new class extends Migration
{
    /** Таблица-связка => FK-колонка сущности. */
    private const PIVOTS = [
        'image_m2m_item'  => 'item_id',
        'image_m2m_store' => 'store_id',
    ];

    public function up(): void
    {
        foreach (self::PIVOTS as $table => $key) {
            // EXISTS на более раннюю строку той же пары: строка удаляется,
            // только если дубль уже есть, иначе удалились бы все привязки
            // подряд. NULLы в ключах не группируются и остаются нетронутыми:
            // такая строка не привязана ни к чему и разбирается отдельно.
            DB::statement(sprintf(
                'DELETE FROM %1$s d
                 WHERE d.image_id IS NOT NULL
                   AND d.%2$s IS NOT NULL
                   AND EXISTS (
                       SELECT 1 FROM %1$s earlier
                       WHERE earlier.image_id = d.image_id
                         AND earlier.%2$s = d.%2$s
                         AND earlier.id < d.id
                   )',
                $table,
                $key
            ));
        }
    }

    public function down(): void
    {
        // Дубли удалены необратимо: восстанавливать их нечем и незачем.
    }
};
