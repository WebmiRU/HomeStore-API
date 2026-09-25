<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_preset', function (Blueprint $table) {
            // Системный шаблон: виден всем, но не редактируется и не удаляется.
            // Отдельный флаг, а не трактовка user_id IS NULL: на user_id стоит
            // ON DELETE SET NULL, и после удаления пользователя его шаблоны
            // стали бы неудаляемыми системными.
            $table->boolean('is_system')->default(false)->after('user_id');

            // Только код, без подписи — для мелких этикеток без текста.
            $table->boolean('show_text')->default(true)->after('barcode_size');
        });

        // Частичный уникальный индекс: в обычном уникальном индексе Postgres
        // считает NULL-ы различными, поэтому (user_id IS NULL, title) не
        // защищает от дублей и повторный запуск сидера наплодил бы копии.
        DB::statement(
            'CREATE UNIQUE INDEX label_preset_system_title_unique ON label_preset (title) WHERE is_system'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS label_preset_system_title_unique');

        Schema::table('label_preset', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'show_text']);
        });
    }
};
