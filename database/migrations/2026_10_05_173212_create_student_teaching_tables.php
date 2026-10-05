<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_plans', function (Blueprint $table): void {
            $this->studentRecord($table);
            $table->string('title', 200);
            $table->text('goals');
            $table->text('focus_areas')->nullable();
            $table->string('current_level', 100)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->date('start_date');
            $table->boolean('student_visible')->default(false);
            $table->index(['student_id', 'student_visible', 'status']);
        });
        Schema::create('learning_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_plan_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('homeworks', function (Blueprint $table): void {
            $this->studentRecord($table, true);
            $table->string('title', 200);
            $table->text('instructions');
            $table->date('assigned_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['assigned', 'in_progress', 'submitted', 'completed'])->default('assigned');
            $table->boolean('student_visible')->default(false);
            $table->text('feedback')->nullable();
            $table->text('student_response')->nullable();
            $table->string('url', 2048)->nullable();
            $table->foreignId('resource_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lesson_material_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['student_id', 'student_visible', 'status', 'due_date']);
        });
        Schema::create('tutor_preparations', function (Blueprint $table): void {
            $this->studentRecord($table, true);
            $table->text('body');
        });
        Schema::create('resource_assignments', function (Blueprint $table): void {
            $this->studentRecord($table, true);
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->text('instructions')->nullable();
            $table->boolean('student_visible')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->index(['student_id', 'student_visible', 'reviewed_at'], 'resource_assignment_portal');
        });
        Schema::create('student_error_logs', function (Blueprint $table): void {
            $this->studentRecord($table, true);
            $table->enum('category', ['pronunciation', 'vocabulary', 'grammar']);
            $table->string('mistake', 500);
            $table->text('correction');
            $table->text('notes')->nullable();
            $table->enum('status', ['practising', 'improved', 'resolved'])->default('practising');
            $table->boolean('student_visible')->default(false);
            $table->index(['student_id', 'student_visible', 'status'], 'error_log_portal');
        });
        Schema::create('teaching_tags', function (Blueprint $table): void {
            $this->studentRecord($table, true);
            $table->string('label', 40);
            $table->boolean('student_visible')->default(false);
            $table->index(['student_id', 'booking_id', 'label']);
        });
        Schema::create('lesson_feedback', function (Blueprint $table): void {
            $this->studentRecord($table);
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->unique('booking_id');
        });
        Schema::create('student_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('deduplication_key', 64)->unique();
            $table->string('type', 40);
            $table->string('title', 200);
            $table->text('message');
            $table->string('link', 500);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'read_at', 'created_at']);
        });
    }

    private function studentRecord(Blueprint $table, bool $lesson = false): void
    {
        $table->id();
        $table->foreignId('student_id')->constrained()->restrictOnDelete();
        if ($lesson) {
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
        }
        $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
        $table->timestamps();
    }

    public function down(): void
    {
        foreach (['student_notifications', 'lesson_feedback', 'teaching_tags', 'student_error_logs', 'resource_assignments', 'tutor_preparations', 'homeworks', 'learning_milestones', 'learning_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
