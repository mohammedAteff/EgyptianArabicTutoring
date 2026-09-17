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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('status', 32)->default('draft')->index(); // 'draft', 'published', 'archived'
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('og_image_path')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisable_type');
            $table->unsignedBigInteger('revisable_id');
            $table->unsignedInteger('revision_number')->default(1);
            $table->string('title')->nullable();
            $table->json('content');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('status', 32)->default('draft'); // 'draft', 'published'
            $table->timestamps();

            $table->index(['revisable_type', 'revisable_id']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->json('dimensions')->nullable();
            $table->string('alt_text')->nullable();
            $table->timestamps();
        });

        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 50); // instagram, tiktok, youtube, telegram, whatsapp
            $table->string('url_or_phone');
            $table->string('label');
            $table->text('default_message')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->longText('value')->nullable();
            $table->string('group', 50)->default('general')->index();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('social_links');
        Schema::dropIfExists('media');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('pages');
    }
};
