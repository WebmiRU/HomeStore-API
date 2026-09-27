<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мягкое удаление для доменных сущностей: удалённая строка остаётся в базе,
 * но скрыта глобальным скоупом модели.
 *
 * Сюда попадают только сущности, у которых в интерфейсе есть удаление и
 * которые имеет смысл когда-нибудь восстановить или удалить совсем:
 *
 *   склад, хранилище, предмет, категория, производитель, единица измерения,
 *   свойство, группа свойств, справочник, значение справочника, шаблон
 *   этикеток, список этикеток, пользователь.
 *
 * Чего здесь нет и почему:
 *
 *   - code — коды не самостоятельные сущности, а привязки к предмету или
 *     хранилищу; удаляются физически каскадом вместе с ними, а «осиротевшие»
 *     чистятся отдельной страницей. Мягкое удаление и неполный уникальный
 *     индекс code_label_list_code_unique дали бы блокировку: удалённый с
 *     мягким код нельзя было бы добавить в список этикеток заново.
 *   - item_property — значения свойств перезаписываются целиком при каждом
 *     сохранении предмета (ItemPropertyService::sync), и мягкое удаление
 *     копило бы пустые строки при каждом сохранении.
 *   - image и pivot-таблицы — это файлы и связи, а не записи каталога.
 *   - user_token — отзыв уже сделан флагом revoked_at, удалять нечего.
 *   - stock_operation — удаления у операций нет, есть только отмена.
 *
 * Тип колонки — timestamp(0) without time zone, как у created_at/updated_at
 * во всех этих таблицах: отдельный тип выглядел бы в разнобой.
 */
return new class extends Migration
{
    /** Таблицы, где удаление становится мягким. */
    private const TABLES = [
        'warehouse',
        'store',
        'item',
        'category',
        'vendor',
        'unit',
        'property',
        'property_group',
        'dictionary',
        'dictionary_value',
        'label_preset',
        'label_list',
        'user',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('deleted_at');
            });
        }
    }
};
