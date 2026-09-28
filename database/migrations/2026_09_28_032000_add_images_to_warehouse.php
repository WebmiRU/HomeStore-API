<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Фотографии складов.
 *
 * Связка устроена как у предметов и хранилищ — те же колонки, тот же
 * порядок по весу, то же каскадное удаление. Снимок склада нужен, чтобы
 * узнать его с парковки: в списке складов фотографии не было вовсе, и чтобы
 * посмотреть, приходилось открывать карточку и смотреть на пустое поле.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_m2m_warehouse', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('image_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->string('alt')->nullable();
            $table->unsignedInteger('weight')->nullable()->default(0);

            $table->foreign('image_id')
                ->references('id')
                ->on('image')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouse')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_m2m_warehouse');
    }
};
