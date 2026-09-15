<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image', function (Blueprint $table) {
            $table->text('path')->nullable();
            $table->text('original_name')->nullable();
            $table->string('mime')->nullable();
        });

        Schema::table('image_m2m_item', function (Blueprint $table) {
            $table->unsignedBigInteger('image_id')->nullable()->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->string('alt')->nullable();
            $table->unsignedInteger('weight')->nullable()->default(0);

            $table->foreign('image_id')
                ->references('id')
                ->on('image')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')
                ->on('item')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('image_m2m_sotre', function (Blueprint $table) {
            $table->unsignedBigInteger('image_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('alt')->nullable();
            $table->unsignedInteger('weight')->nullable()->default(0);

            $table->foreign('image_id')
                ->references('id')
                ->on('image')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('store_id')
                ->references('id')
                ->on('store')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('image_m2m_sotre', function (Blueprint $table) {
            $table->dropForeign(['image_id']);
            $table->dropForeign(['store_id']);
            $table->dropColumn(['image_id', 'store_id', 'alt', 'weight']);
        });

        Schema::table('image_m2m_item', function (Blueprint $table) {
            $table->dropForeign(['image_id']);
            $table->dropForeign(['item_id']);
            $table->dropColumn(['image_id', 'item_id', 'alt', 'weight']);
        });

        Schema::table('image', function (Blueprint $table) {
            $table->dropColumn(['path', 'original_name', 'mime']);
        });
    }
};