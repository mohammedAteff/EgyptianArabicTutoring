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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('headline', 255);
            $table->text('subheadline')->nullable();
            $table->string('cta_text', 100);
            $table->string('cta_url', 255);
            $table->string('banner_image_path', 255)->nullable();
            $table->boolean('is_active')->default(false);
            $table->enum('display_type', ['top_bar', 'floating_modal', 'inline_card']);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('has_countdown')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
