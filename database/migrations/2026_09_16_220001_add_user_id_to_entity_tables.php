<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['code', 'item', 'label_list', 'label_preset', 'store'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('user_id')->nullable()->index();
                $blueprint->foreign('user_id')
                    ->references('id')
                    ->on('user')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        // Названия шаблонов/этикеток уникальны в рамках пользователя,
        // а не глобально (у каждого пользователя свой набор).
        Schema::table('label_preset', function (Blueprint $blueprint) {
            $blueprint->dropUnique('label_preset_title_unique');
            $blueprint->unique(['user_id', 'title'], 'label_preset_user_id_title_unique');
        });

        Schema::table('label_list', function (Blueprint $blueprint) {
            $blueprint->dropUnique('label_list_title_unique');
            $blueprint->unique(['user_id', 'title'], 'label_list_user_id_title_unique');
        });
    }

    public function down(): void
    {
        Schema::table('label_preset', function (Blueprint $blueprint) {
            $blueprint->dropUnique('label_preset_user_id_title_unique');
            $blueprint->unique('title', 'label_preset_title_unique');
        });

        Schema::table('label_list', function (Blueprint $blueprint) {
            $blueprint->dropUnique('label_list_user_id_title_unique');
            $blueprint->unique('title', 'label_list_title_unique');
        });

        foreach (['code', 'item', 'label_list', 'label_preset', 'store'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign("{$table}_user_id_foreign");
                $blueprint->dropColumn('user_id');
            });
        }
    }
};