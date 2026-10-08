<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_lessons', fn (Blueprint $table) => $table->json('learning_rules')->nullable());
        Schema::create('lms_lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lms_lessons')->restrictOnDelete();
            $table->char('requirement_hash', 64);
            $table->timestamp('started_at');
            $table->timestamp('manual_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'lesson_id', 'requirement_hash'], 'lms_progress_identity');
        });
        Schema::create('lms_video_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('block_id')->constrained('lms_lesson_blocks')->restrictOnDelete();
            $table->char('media_hash', 64);
            $table->unsignedInteger('duration_milliseconds');
            $table->json('watched_ranges');
            $table->unsignedInteger('position_milliseconds')->default(0);
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('playing')->default(false);
            $table->timestamp('sampled_at', 3)->nullable();
            $table->char('watch_token_hash', 64)->nullable();
            $table->foreignId('lease_id')->nullable()->constrained('lms_playback_leases')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'block_id', 'media_hash'], 'lms_watch_identity');
        });
        foreach (['lms_quiz_attempts', 'lms_assignment_submissions'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
                $table->foreignId('block_id')->constrained('lms_lesson_blocks')->restrictOnDelete();
                $table->char('definition_hash', 64);
                $table->uuid('request_key');
                $table->unsignedInteger('number');
                $table->unsignedInteger('original_number');
                $table->json('definition');
                $table->string('status', 24);
                $table->unsignedInteger('lock_version')->default(1);
                $table->foreignId('reviewed_by')->nullable()->constrained('administrators')->nullOnDelete();
                $table->text('feedback')->nullable();
                if ($name === 'lms_quiz_attempts') {
                    $table->json('answers')->nullable();
                    $table->json('marks')->nullable();
                    $table->decimal('score', 5, 2)->nullable();
                    $table->boolean('passed')->nullable();
                    $table->timestamp('submitted_at')->nullable();
                } else {
                    $table->string('kind', 24);
                    $table->text('body')->nullable();
                    $table->text('url')->nullable();
                    $table->string('path')->nullable();
                    $table->string('mime_type', 100)->nullable();
                    $table->unsignedInteger('byte_size')->nullable();
                    $table->char('sha256', 64)->nullable();
                }
                $table->timestamps();
                $table->unique(['student_id', 'request_key'], $name.'_request');
                $table->unique(['student_id', 'block_id', 'definition_hash', 'number'], $name.'_number');
                $table->index(['status', 'block_id']);
            });
        }
        DB::statement('ALTER TABLE lms_lesson_blocks DROP CONSTRAINT lms_block_payload');
        DB::statement("ALTER TABLE lms_lesson_blocks ADD CONSTRAINT lms_block_payload CHECK (
            kind IN ('video','bunny_video','youtube_video','external_video','rich_text','image','file','resource','external_link','quiz','assignment')
            AND ((status IN ('placeholder','withdrawn') AND resource_id IS NULL AND asset_id IS NULL AND video_asset_id IS NULL AND payload IS NULL)
            OR (status = 'ready' AND (
                (kind = 'video' AND video_asset_id IS NOT NULL AND resource_id IS NULL AND asset_id IS NULL AND payload IS NULL)
                OR (kind = 'resource' AND resource_id IS NOT NULL AND asset_id IS NULL AND video_asset_id IS NULL AND payload IS NULL)
                OR (kind = 'file' AND ((resource_id IS NOT NULL AND asset_id IS NULL) OR (resource_id IS NULL AND asset_id IS NOT NULL)) AND video_asset_id IS NULL AND payload IS NOT NULL)
                OR (kind = 'image' AND resource_id IS NULL AND asset_id IS NOT NULL AND video_asset_id IS NULL AND payload IS NOT NULL)
                OR (kind IN ('rich_text','external_link','youtube_video','external_video') AND resource_id IS NULL AND asset_id IS NULL AND video_asset_id IS NULL AND payload IS NOT NULL)
                OR (kind = 'quiz' AND resource_id IS NULL AND asset_id IS NULL AND video_asset_id IS NULL AND payload IS NOT NULL
                    AND JSON_CONTAINS_PATH(payload, 'all', '$.title', '$.passing_score', '$.attempt_limit', '$.review_policy', '$.questions') = 1
                    AND JSON_TYPE(JSON_EXTRACT(payload, '$.questions')) = 'ARRAY' AND JSON_LENGTH(JSON_EXTRACT(payload, '$.questions')) BETWEEN 1 AND 100)
                OR (kind = 'assignment' AND resource_id IS NULL AND asset_id IS NULL AND video_asset_id IS NULL AND payload IS NOT NULL
                    AND JSON_CONTAINS_PATH(payload, 'all', '$.title', '$.instructions', '$.types') = 1
                    AND JSON_TYPE(JSON_EXTRACT(payload, '$.types')) = 'ARRAY' AND JSON_LENGTH(JSON_EXTRACT(payload, '$.types')) BETWEEN 1 AND 5)))))");
        DB::statement("ALTER TABLE lms_quiz_attempts ADD CONSTRAINT lms_quiz_state CHECK (status IN ('started','pending_review','graded','erased') AND (score IS NULL OR score BETWEEN 0 AND 100))");
        DB::statement("ALTER TABLE lms_assignment_submissions ADD CONSTRAINT lms_submission_state CHECK (status IN ('submitted','under_review','needs_revision','approved','erased') AND kind IN ('text','file','audio','video','external_link'))");
    }

    public function down(): void
    {
        throw new LogicException('Learning evidence requires a reviewed forward fix; rollback would destroy history.');
    }
};
