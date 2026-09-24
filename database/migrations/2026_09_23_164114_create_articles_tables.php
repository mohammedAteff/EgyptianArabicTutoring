<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt');
            $table->longText('body');
            $table->string('featured_image_path')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('author_id')->constrained('administrators')->restrictOnDelete();
            $table->string('locale', 8)->default('en')->index();
            $table->uuid('translation_group_id')->nullable()->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['translation_group_id', 'locale']);
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->json('snapshot');
            $table->foreignId('revised_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('article_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->string('old_slug')->unique();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['article_slug_redirects', 'article_revisions', 'articles'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Cannot drop populated article history.');
            }
        }

        Schema::dropIfExists('article_slug_redirects');
        Schema::dropIfExists('article_revisions');
        Schema::dropIfExists('articles');
    }
};
