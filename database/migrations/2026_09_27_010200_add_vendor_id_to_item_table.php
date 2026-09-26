<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable();

            // nullOnDelete, как у category_id: удаление поставщика не должно
            // уносить с собой предметы. Предмет остаётся, теряя одно поле.
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendor')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->dropForeign('item_vendor_id_foreign');
            $table->dropColumn('vendor_id');
        });
    }
};
