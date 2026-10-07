<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $courseType = strtoupper(bin2hex('App\\Domains\\Lms\\Models\\Course'));
        Schema::table('content_revisions', function (Blueprint $table) use ($courseType): void {
            $table->unsignedBigInteger('lms_course_key')->nullable()->virtualAs("CASE WHEN HEX(revisable_type) = '{$courseType}' THEN revisable_id ELSE NULL END");
            $table->unsignedBigInteger('lms_draft_course_key')->nullable()->virtualAs("CASE WHEN HEX(revisable_type) = '{$courseType}' AND status = 'draft' THEN revisable_id ELSE NULL END");
            $table->unique(['lms_course_key', 'revision_number'], 'lms_revision_number');
            $table->unique('lms_draft_course_key', 'lms_revision_draft');
        });
        Schema::create('lms_course_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $table->foreignId('content_revision_id')->unique()->constrained('content_revisions')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('content_hash', 64);
            $table->dateTime('published_at');
            $table->timestamps();
            $table->unique(['course_id', 'revision_number'], 'lms_releases_number');
            $table->unique(['id', 'course_id'], 'lms_releases_parent');
            $table->index(['course_id', 'published_at', 'id'], 'lms_releases_history');
        });
        Schema::table('lms_courses', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_release_id')->nullable();
            $table->foreign(['current_release_id', 'id'], 'lms_courses_release')->references(['id', 'course_id'])->on('lms_course_releases')->restrictOnDelete();
            $table->index(['status', 'id'], 'lms_courses_studio_list');
        });
        Schema::create('lms_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $table->enum('kind', ['image', 'file']);
            $table->enum('status', ['active', 'withdrawn'])->default('active');
            $table->string('disk', 16)->default('local');
            $table->string('path', 255)->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->string('sha256', 64);
            $table->string('original_name', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamps();
            $table->unique(['disk', 'path'], 'lms_assets_path');
            $table->index(['course_id', 'status', 'id'], 'lms_assets_owner');
        });
        DB::statement("ALTER TABLE lms_assets ADD CONSTRAINT lms_asset_storage CHECK (disk = 'local' AND (status <> 'active' OR path IS NOT NULL) AND byte_size > 0)");
        Schema::table('lms_lesson_blocks', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->constrained('lms_assets')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE lms_lesson_blocks DROP CONSTRAINT lms_block_payload');
        DB::statement("ALTER TABLE lms_lesson_blocks ADD CONSTRAINT lms_block_payload CHECK (
            kind IN ('bunny_video','youtube_video','external_video','rich_text','image','file','resource','external_link','quiz','assignment')
            AND ((status IN ('placeholder','withdrawn') AND resource_id IS NULL AND asset_id IS NULL AND payload IS NULL)
            OR (status = 'ready' AND (
                (kind = 'resource' AND resource_id IS NOT NULL AND asset_id IS NULL AND payload IS NULL)
                OR (kind = 'file' AND ((resource_id IS NOT NULL AND asset_id IS NULL) OR (resource_id IS NULL AND asset_id IS NOT NULL)) AND payload IS NOT NULL)
                OR (kind = 'image' AND resource_id IS NULL AND asset_id IS NOT NULL AND payload IS NOT NULL)
                OR (kind IN ('rich_text','external_link','youtube_video','external_video') AND resource_id IS NULL AND asset_id IS NULL AND payload IS NOT NULL)))))");
    }

    public function down(): void
    {
        if (DB::table('lms_course_releases')->exists() || DB::table('lms_assets')->exists()
            || DB::table('content_revisions')->where('revisable_type', 'App\\Domains\\Lms\\Models\\Course')->exists()
            || DB::table('lms_lesson_blocks')->whereIn('kind', ['image', 'file'])->where('status', 'ready')->exists()) {
            throw new RuntimeException('Course Studio history requires reviewed recovery; destructive rollback refused.');
        }
        DB::statement('ALTER TABLE lms_lesson_blocks DROP CONSTRAINT lms_block_payload');
        Schema::table('lms_lesson_blocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_id');
        });
        DB::statement("ALTER TABLE lms_lesson_blocks ADD CONSTRAINT lms_block_payload CHECK (
            kind IN ('bunny_video','youtube_video','external_video','rich_text','image','file','resource','external_link','quiz','assignment')
            AND ((status IN ('placeholder','withdrawn') AND resource_id IS NULL AND payload IS NULL)
                OR (status = 'ready' AND ((kind = 'resource' AND resource_id IS NOT NULL AND payload IS NULL)
                    OR (kind IN ('rich_text','external_link','youtube_video','external_video') AND resource_id IS NULL AND payload IS NOT NULL)))))");
        Schema::table('lms_courses', function (Blueprint $table): void {
            $table->dropForeign('lms_courses_release');
            $table->dropColumn('current_release_id');
            $table->dropIndex('lms_courses_studio_list');
        });
        Schema::dropIfExists('lms_assets');
        Schema::dropIfExists('lms_course_releases');
        Schema::table('content_revisions', function (Blueprint $table): void {
            $table->dropUnique('lms_revision_number');
            $table->dropUnique('lms_revision_draft');
            $table->dropColumn(['lms_course_key', 'lms_draft_course_key']);
        });
    }
};
