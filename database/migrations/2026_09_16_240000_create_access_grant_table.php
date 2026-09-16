<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_grant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('user')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user')->cascadeOnDelete();
            $table->string('entity_type', 50)->default('warehouse');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('rights');
            $table->timestamps();

            $table->unique(['owner_id', 'entity_type', 'entity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_grant');
    }
};