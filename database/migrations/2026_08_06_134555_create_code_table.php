<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->text('code')->unique();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
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

        DB::statement('ALTER TABLE "code" ADD CONSTRAINT code_xor_check CHECK (store_id IS NULL OR item_id IS NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code');
    }
};
