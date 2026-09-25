<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE image_format AS ENUM ('webp', 'avif', 'jpeg')");
        DB::statement("CREATE TYPE image_crop AS ENUM ('cover', 'contain')");

        DB::statement(<<<'SQL'
            CREATE TABLE "thumbnail" (
                "id" bigserial NOT NULL,
                "format" image_format NOT NULL,
                "width" integer NOT NULL,
                "height" integer NOT NULL,
                "crop" image_crop NOT NULL,
                "key" varchar(255) NOT NULL,
                "created_at" timestamp(0) without time zone NULL,
                "updated_at" timestamp(0) without time zone NULL,
                CONSTRAINT "thumbnail_pkey" PRIMARY KEY ("id"),
                CONSTRAINT "thumbnail_key_unique" UNIQUE ("key")
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('thumbnail');

        DB::statement('DROP TYPE IF EXISTS image_crop');
        DB::statement('DROP TYPE IF EXISTS image_format');
    }
};
