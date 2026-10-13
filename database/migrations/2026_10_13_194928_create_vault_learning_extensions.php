<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->restrictOnDelete();
            $table->text('body')->nullable();
            $table->uuid('submission_key')->unique();
            $table->timestamp('submitted_at');
            $table->index(['homework_id', 'submitted_at']);
        });
        Schema::table('homeworks', function (Blueprint $table): void {
            $table->enum('status', ['assigned', 'in_progress', 'submitted', 'reviewed', 'completed'])->default('assigned')->change();
        });
        Schema::create('learning_skills', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 120)->unique();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('student_skill_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('learning_skill_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->enum('state', ['needs_work', 'developing', 'comfortable', 'strong']);
            $table->text('notes')->nullable();
            $table->boolean('student_visible')->default(false);
            $table->timestamp('assessed_at');
            $table->index(['student_id', 'assessed_at']);
        });
        Schema::create('resource_collection_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('resource_collection_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->boolean('student_visible')->default(true);
            $table->timestamps();
            $table->unique(['student_id', 'resource_collection_id'], 'collection_student_unique');
        });
        Schema::create('student_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['resource', 'note', 'homework']);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();
            $table->unique(['student_id', 'kind', 'target_id'], 'student_bookmark_unique');
        });
        Schema::create('student_progress_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->text('summary');
            $table->text('next_goals')->nullable();
            $table->boolean('student_visible')->default(false);
            $table->timestamps();
            $table->index(['student_id', 'date_from']);
        });
        Schema::create('student_portal_logins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('timezone', 100)->nullable();
            $table->timestamp('authenticated_at');
            $table->index(['student_id', 'authenticated_at']);
        });
    }

    public function down(): void
    {
        foreach (['student_portal_logins', 'student_progress_reports', 'student_bookmarks', 'resource_collection_assignments', 'student_skill_assessments', 'learning_skills', 'homework_submissions'] as $table) {
            Schema::dropIfExists($table);
        }
        // Reviewed homework remains valid during rollback to preserve its existing status.
    }
};
