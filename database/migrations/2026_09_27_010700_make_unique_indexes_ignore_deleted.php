<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Уникальные индексы каталогов становятся частичными: уникальность действует
 * только среди живых записей.
 *
 * Смысл: мягкое удаление оставляет строку в таблице, а вместе с ней и её
 * место в уникальном индексе. Без этого удалил бы категорию «Манометры» —
 * и больше не смог бы завести такую же: сервер отвечал бы «уже есть» на
 * имя, которого в интерфейсе не видно. С WHERE deleted_at IS NULL удалённая
 * строка из индекса выпадает, и имя свободно.
 *
 * Частичного уникального ограничения в PostgreSQL не бывает — только частичный
 * индекс, поэтому ограничения сначала снимаются, а вместо них заводятся
 * индексы. Восстанавливать их в down() надо тоже по-разному: восемь из девяти
 * созданы через $table->unique() и живут в pg_constraint, и DROP INDEX по ним
 * не проходит («dependent objects still exist»); девятый, у категории,
 * создан сырым CREATE UNIQUE INDEX и снимается как индекс.
 *
 * Чего здесь нет и почему:
 *
 *   - user_email_unique — по решению владельца e-mail удалённого пользователя
 *     остаётся занятым: освобождать его будет «корзина» жёстким удалением.
 *   - access_grant_owner_id_entity_type_entity_id_user_id_unique — выдачи
 *     прав удаляются физически, мягкого удаления у них нет.
 *   - label_preset_system_title_unique — системные шаблоны удалять нельзя
 *     (LabelPresetController отдаёт 403), мягкое удаление до них не доходит.
 *   - code_label_list_code_unique — коды не мягкие, см. соседнюю миграцию
 *     с deleted_at.
 *
 * Пересоздание индексов идёт под блокировкой: на живой базе это короткая, но
 * реальная блокировка таблиц, поэтому катнуть лучше в момент, когда с
 * каталогом не работают.
 */
return new class extends Migration
{
    /**
     * Имя ограничения => [таблица, выражение, способ снятия].
     *
     * 'constraint' — pg_constraint, снимается ALTER TABLE ... DROP CONSTRAINT;
     * 'index'      — обычный индекс, снимается DROP INDEX.
     */
    private const TARGETS = [
        'category_user_parent_title_unique' => [
            'table' => 'category',
            'columns' => 'user_id, COALESCE(parent_id, 0), title',
            'kind' => 'index',
        ],
        'dictionary_user_id_title_unique' => [
            'table' => 'dictionary',
            'columns' => 'user_id, title',
            'kind' => 'constraint',
        ],
        'dictionary_value_dictionary_id_title_unique' => [
            'table' => 'dictionary_value',
            'columns' => 'dictionary_id, title',
            'kind' => 'constraint',
        ],
        'label_list_user_id_title_unique' => [
            'table' => 'label_list',
            'columns' => 'user_id, title',
            'kind' => 'constraint',
        ],
        'label_preset_user_id_title_unique' => [
            'table' => 'label_preset',
            'columns' => 'user_id, title',
            'kind' => 'constraint',
        ],
        'property_user_id_title_unique' => [
            'table' => 'property',
            'columns' => 'user_id, title',
            'kind' => 'constraint',
        ],
        'property_group_user_id_title_unique' => [
            'table' => 'property_group',
            'columns' => 'user_id, title',
            'kind' => 'constraint',
        ],
        'unit_user_id_title_short_unique' => [
            'table' => 'unit',
            'columns' => 'user_id, title_short',
            'kind' => 'constraint',
        ],
        'vendor_user_id_title_unique' => [
            'table' => 'vendor',
            'columns' => 'user_id, title',
            'kind' => 'constraint',
        ],
    ];

    public function up(): void
    {
        foreach (self::TARGETS as $name => $target) {
            $this->dropUnique($name, $target);

            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s (%s) WHERE deleted_at IS NULL',
                $name,
                $target['table'],
                $target['columns']
            ));
        }
    }

    public function down(): void
    {
        foreach (self::TARGETS as $name => $target) {
            $this->dropUnique($name, $target);

            if ($target['kind'] === 'constraint') {
                DB::statement(sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s UNIQUE (%s)',
                    $target['table'],
                    $name,
                    $target['columns']
                ));

                continue;
            }

            DB::statement(sprintf('CREATE UNIQUE INDEX %s ON %s (%s)', $name, $target['table'], $target['columns']));
        }
    }

    /**
     * Снимает уникальность по имени, не разбираясь, ограничение это или
     * индекс: у таблиц проекта оба вида встречаются, а попытка снять не тем
     * способом падает с «dependent objects still exist».
     *
     * @param  array{table: string, columns: string, kind: string}  $target
     */
    private function dropUnique(string $name, array $target): void
    {
        if ($target['kind'] === 'constraint') {
            DB::statement(sprintf(
                'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
                $target['table'],
                $name
            ));

            return;
        }

        DB::statement(sprintf('DROP INDEX IF EXISTS %s', $name));
    }
};
