<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Фотографии категорий.
 *
 * Связка устроена как у предметов, складов и хранилищ — те же колонки, тот же
 * порядок по весу, то же каскадное удаление. Категория видна в списке и в
 * «Каталоге» одним взглядом, и без фотографии она там была просто строкой
 * текста: видеть, что в категории лежит, приходилось, открывая каждую.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_m2m_category', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('image_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('alt')->nullable();
            $table->unsignedInteger('weight')->nullable()->default(0);

            $table->foreign('image_id')
                ->references('id')
                ->on('image')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('category_id')
                ->references('id')
                ->on('category')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_m2m_category');
    }
};