<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_token', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('user')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('name')->nullable();
            $table->string('token', 128)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->ipAddress('last_used_ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_token');
    }
};