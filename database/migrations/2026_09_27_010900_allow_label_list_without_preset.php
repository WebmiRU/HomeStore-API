<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Список этикеток переживает удаление шаблона: колонка ссылки становится
 * nullable.
 *
 * Миграция рядом перевела FK на nullOnDelete, но одной её мало: колонка была
 * объявлена NOT NULL, и удаление шаблона падало бы с нарушением ограничения
 * раньше, чем в дело успевал вступить обработчик FK. Вышло на это при
 * первой же попытке завести набор без шаблона.
 *
 * NOT NULL здесь и был по сути требованием целостности, но смысла в нём
 * после решения владельца нет: у этикеток без шаблона есть законный
 * состояние — их можно напечатать позже, назначив другой шаблон. Печать
 * без шаблона отдаёт 422 (LabelListController::generate).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE label_list ALTER COLUMN label_preset_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Вернуть NOT NULL можно только если нет списков без шаблона:
        // иначе ограничение поставить не удастся. Поэтому сначала отчитываем
        //ся о таких списках, а не падаем с непонятной ошибкой.
        $orphaned = DB::table('label_list')->whereNull('label_preset_id')->count();

        if ($orphaned > 0) {
            throw new RuntimeException(
                "Нельзя вернуть NOT NULL: списков без шаблона — {$orphaned}. "
                . 'Назначьте им шаблон и повторите откат.'
            );
        }

        DB::statement('ALTER TABLE label_list ALTER COLUMN label_preset_id SET NOT NULL');
    }
};
