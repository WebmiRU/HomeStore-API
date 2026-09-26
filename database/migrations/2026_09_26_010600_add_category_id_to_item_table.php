<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable();

            // nullOnDelete, а не cascade (как у product.catalog_id в base220):
            // удаление категории не должно уносить с собой предметы. Предмет
            // просто остаётся без категории — это потеря одного поля, а не
            // всего имущества.
            $table->foreign('category_id')
                ->references('id')
                ->on('category')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->dropForeign('item_category_id_foreign');
            $table->dropColumn('category_id');
        });
    }
};
