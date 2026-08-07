<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_list', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->foreignId('label_preset_id')->constrained('label_preset')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('label_list_m2m_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_list_id')->constrained('label_list')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('item')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['label_list_id', 'item_id']);
        });

        Schema::create('label_list_m2m_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_list_id')->constrained('label_list')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('store')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['label_list_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_list_m2m_store');
        Schema::dropIfExists('label_list_m2m_item');
        Schema::dropIfExists('label_list');
    }
};
