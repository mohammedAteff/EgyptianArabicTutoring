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
        Schema::create('resource_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('resource_categories');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->text('full_description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type', 20)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('cover_image_path')->nullable();
            $table->string('status', 32)->default('draft')->index(); // 'draft', 'published', 'archived'
            $table->boolean('featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('resource_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts');
            $table->foreignId('resource_id')->constrained('resources');
            $table->string('visitor_token', 64)->nullable()->index();
            $table->string('session_token', 64)->nullable()->index();
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('content')->nullable();
            $table->string('term')->nullable();
            $table->string('landing_page')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('resource_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources');
            $table->foreignId('contact_id')->nullable()->constrained('contacts');
            $table->foreignId('request_id')->nullable()->constrained('resource_requests');
            $table->string('visitor_token', 64)->nullable()->index();
            $table->string('session_token', 64)->nullable()->index();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_downloads');
        Schema::dropIfExists('resource_requests');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('resource_categories');
    }
};
