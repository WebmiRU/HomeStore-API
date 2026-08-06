<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('code', function (Blueprint $table) {
            $table->id();
            $table->uuid('code')->unique();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('store_id');
            $table->timestamps();

            $table->foreign('item_id')
                ->references('id')
                ->on('item')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('store_id')
                ->references('id')
                ->on('store')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code');
    }
};
