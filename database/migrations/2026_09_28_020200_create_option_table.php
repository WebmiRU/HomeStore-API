<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Настройки пользователя: одна строка на человека.
 *
 * Таблица называется option, а не user_setting, по решению владельца проекта:
 * сущность — «настройки», и страница с ними тоже называется «Настройки».
 *
 * Про колонки:
 *
 * - menu_order и menu_hidden — плоские списки ключей пунктов меню, верхнего
 *   уровня и вложенных вперемешку. Уровень у ключа известен только меню, и
 *   хранить его в базе незачем: сортировка берёт подмножество ключей своего
 *   уровня. Пустой menu_order означает «порядок как в приложении», а не
 *   «порядок пустой»: пока человек меню не трогал, хранить нечего, и
 *   перестановка пунктов в новой версии приложения применяется сама;
 * - operation_mode переехал сюда из sessionStorage: режим работы
 *   (поиск/пополнение/списание) должен переживать вкладку и другое устройство,
 *   а не жить до закрытия браузера;
 * - значений по умолчанию в базе нет ни одного: у пользователя, который
 *   ничего не сохранял, строки не существует вовсе, и GET отдаёт дефолты из
 *   кода, ничего не записывая.
 *
 * Мягкого удаления нет, как у user_token и sessions: настройки не удаляют, их
 * сбрасывают к умолчаниям, и это та же самая строка.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            // Ключи пунктов меню в порядке пользователя. Верхний уровень и
            // вложенные пункты лежат в одном списке: уровень определяется
            // самим меню, а хранить его здесь значит завести колонку на
            // каждый пункт и миграцию на каждое его переименование.
            $table->jsonb('menu_order')->default('[]');
            $table->jsonb('menu_hidden')->default('[]');

            $table->string('operation_mode', 16)->default('search');
            $table->boolean('show_code_block')->default(true);

            $table->timestamps();

            $table->unique('user_id', 'option_user_id_unique');

            // Настройки без пользователя бессмысленны (как единицы измерения
            // и категории), поэтому каскад. Пользователя на самом деле не
            // удаляют, но каскад означает правильный смысл, а не
            // «рассчитываем, что случится».
            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        // Что это за списки, БД не знает: jsonb принимает что угодно. Но
        // хотя бы «это массив» проверить можно, иначе сломанный cast тихо
        // превратит настройку в мусор, который нельзя ни прочитать, ни
        // исправить. Ограничения — на уровне БД, а не только в валидации:
        // список ключей пишется json-массивом, и правила запроса видят не всё.
        DB::statement("ALTER TABLE option ADD CONSTRAINT option_menu_order_check CHECK (jsonb_typeof(menu_order) = 'array')");
        DB::statement("ALTER TABLE option ADD CONSTRAINT option_menu_hidden_check CHECK (jsonb_typeof(menu_hidden) = 'array')");
        DB::statement("ALTER TABLE option ADD CONSTRAINT option_operation_mode_check CHECK (operation_mode IN ('search', 'replenish', 'writeoff'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('option');
    }
};
