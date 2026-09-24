<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->enum('prompt_trigger', ['none', 'after_booking', 'after_reschedule', 'next_session_check'])->default('none');
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('can_edit_after_submission')->default(false);
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->constrained('administrators')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('form_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('changelog')->nullable();
            $table->timestamps();
            $table->unique(['form_id', 'version_number']);
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->foreignId('active_version_id')->nullable()->after('created_by')->constrained('form_versions')->nullOnDelete();
        });

        Schema::create('form_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_version_id')->constrained('form_versions')->cascadeOnDelete();
            $table->string('question_key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('question_type', 32);
            $table->boolean('is_required')->default(false);
            $table->boolean('assistant_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('validation_rules')->nullable();
            $table->json('presentation_config')->nullable();
            $table->json('conditional_logic')->nullable();
            $table->timestamps();
            $table->unique(['form_version_id', 'question_key']);
            $table->index(['form_version_id', 'sort_order']);
        });

        Schema::create('form_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_question_id')->constrained('form_questions')->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['form_question_id', 'value']);
        });

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_version_id')->constrained('form_versions')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['draft', 'submitted'])->default('draft')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedInteger('submission_revision')->default(1);
            $table->timestamps();
            $table->unique(['form_version_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('form_submission_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('snapshot_answers');
            $table->dateTime('submitted_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['form_submission_id', 'revision_number'], 'form_sub_rev_version_unique');
        });

        Schema::create('form_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignId('form_question_id')->constrained('form_questions')->restrictOnDelete();
            $table->longText('value_text')->nullable();
            $table->timestamps();
            $table->unique(['form_submission_id', 'form_question_id']);
        });
    }

    public function down(): void
    {
        foreach (['form_answers', 'form_submission_revisions', 'form_submissions', 'form_question_options', 'form_questions', 'form_versions', 'forms'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Cannot drop populated student form data.');
            }
        }

        Schema::dropIfExists('form_answers');
        Schema::dropIfExists('form_submission_revisions');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_question_options');
        Schema::dropIfExists('form_questions');
        Schema::table('forms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_version_id');
        });
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('forms');
    }
};
