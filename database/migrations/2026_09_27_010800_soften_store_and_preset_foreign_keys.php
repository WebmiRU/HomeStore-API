<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Две связи перестают уносить с собой то, что на них ссылается.
 *
 * item.store_id: было CASCADE — жёсткое удаление хранилища удаляло его
 * предметы. Теперь предмет переживает удаление хранилища и остаётся с
 * пустым store_id: удаление папки не должно уничтожать вещи, которые в ней
 * лежали. Коды предмета и его картинки при этом уходят каскадом — они без
 * предмета бессмысленны.
 *
 * label_list.label_preset_id: было CASCADE, а плюс LabelPresetController
 * удалял списки в цикле перед шаблоном. Теперь список переживает шаблон:
 * этикетки остаются в обороте, у него появляется [удалено] в графе «Шаблон»,
 * а шаблон ему назначить можно позже. Печать при этом отдаёт 422 — без
 * шаблона вёрстки не существует.
 *
 * Каскады внутри дерева (store.parent_id, category.parent_id,
 * dictionary_value.dictionary_id) не трогаем: удалил родителя — ушли
 * вложенные, иначе корень зарастает сиротами.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item', function ($table) {
            $table->dropForeign('item_store_id_foreign');
        });

        Schema::table('item', function ($table) {
            $table->foreign('store_id')
                ->references('id')
                ->on('store')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('label_list', function ($table) {
            $table->dropForeign('label_list_label_preset_id_foreign');
        });

        Schema::table('label_list', function ($table) {
            $table->foreign('label_preset_id')
                ->references('id')
                ->on('label_preset')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // Возврат к CASCADE требует, чтобы в обоих местах ничего не висело
        // ссылкой на удалённое: иначе пересоздать ограничение не получится.
        DB::statement('UPDATE item SET store_id = NULL WHERE store_id IS NOT NULL AND deleted_at IS NOT NULL');
        DB::statement('UPDATE label_list SET label_preset_id = NULL WHERE label_preset_id IS NOT NULL AND deleted_at IS NOT NULL');

        Schema::table('item', function ($table) {
            $table->dropForeign('item_store_id_foreign');
        });

        Schema::table('item', function ($table) {
            $table->foreign('store_id')
                ->references('id')
                ->on('store')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('label_list', function ($table) {
            $table->dropForeign('label_list_label_preset_id_foreign');
        });

        Schema::table('label_list', function ($table) {
            $table->foreign('label_preset_id')
                ->references('id')
                ->on('label_preset')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
