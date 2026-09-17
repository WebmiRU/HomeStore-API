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
    }

    public function down(): void
    {
        foreach (['code', 'item', 'label_list', 'label_preset', 'store'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign("{$table}_user_id_foreign");
                $blueprint->dropColumn('user_id');
            });
        }
    }
};