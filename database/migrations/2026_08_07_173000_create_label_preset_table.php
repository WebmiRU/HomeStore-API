<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('font', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('key')->unique();
            $table->timestamps();
        });

        Schema::create('label_preset', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();

            // Page dimensions (mm)
            $table->float('page_width')->default(210.0);
            $table->float('page_height')->default(297.0);

            // Page margins (mm)
            $table->float('page_margin_top')->default(10.0);
            $table->float('page_margin_right')->default(10.0);
            $table->float('page_margin_bottom')->default(10.0);
            $table->float('page_margin_left')->default(10.0);

            // Cell dimensions (mm)
            $table->float('cell_width')->default(78.0);
            $table->float('cell_height')->default(23.0);

            // Cell padding (mm)
            $table->float('cell_pad_top')->default(3.0);
            $table->float('cell_pad_right')->default(5.0);
            $table->float('cell_pad_bottom')->default(5.0);
            $table->float('cell_pad_left')->default(5.0);

            // Barcode
            $table->string('barcode_position')->default('left');
            $table->float('barcode_text_gap')->default(2.0);
            $table->float('barcode_size')->default(13.0);

            // Font — reference to font table
            $table->foreignId('font_id')->nullable()->constrained('font')->nullOnDelete();
            $table->float('font_size_min')->default(5.0);
            $table->float('font_size_max')->default(24.0);
            $table->float('font_size_step')->default(0.5);

            // Line height
            $table->float('line_height_factor')->default(1.25);

            $table->timestamps();
        });

        Schema::create('image_m2m_label_preset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained('image')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('label_preset_id')->constrained('label_preset')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['image_id', 'label_preset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_m2m_label_preset');
        Schema::dropIfExists('label_preset');
        Schema::dropIfExists('font');
    }
};
