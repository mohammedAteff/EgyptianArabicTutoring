<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });
        Schema::table('blogs', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('featured_image_alt', 300)->nullable();
            $table->dateTime('editorial_updated_at')->nullable();
            $table->text('canonical_url')->nullable()->change();
        });
        Schema::create('blog_related_articles', function (Blueprint $table): void {
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->foreignId('related_blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->primary(['blog_id', 'related_blog_id']);
        });
        Schema::create('blog_related_resources', function (Blueprint $table): void {
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_id', 'resource_id']);
        });
        Schema::create('blog_related_games', function (Blueprint $table): void {
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_related_games');
        Schema::dropIfExists('blog_related_resources');
        Schema::dropIfExists('blog_related_articles');
        Schema::table('blogs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['featured_image_alt', 'editorial_updated_at']);
        });
        Schema::dropIfExists('blog_categories');
    }
};
