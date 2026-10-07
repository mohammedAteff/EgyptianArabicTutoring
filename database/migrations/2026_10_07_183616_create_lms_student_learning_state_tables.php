<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_lesson_blocks', function (Blueprint $table): void {
            $table->unique(['id', 'lesson_id'], 'lms_blocks_lesson_parent');
        });
        Schema::create('lms_learning_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->foreign(['lesson_id', 'course_id'], 'lms_visits_lesson')->references(['id', 'course_id'])->on('lms_lessons')->restrictOnDelete();
            $table->dateTime('accessed_at');
            $table->timestamps();
            $table->unique(['student_id', 'course_id']);
            $table->index(['student_id', 'accessed_at', 'id']);
        });
        Schema::create('lms_lesson_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lms_lessons')->restrictOnDelete();
            $table->text('body');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['student_id', 'lesson_id', 'id']);
        });
        Schema::create('lms_lesson_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lms_lessons')->restrictOnDelete();
            $table->unsignedBigInteger('block_id');
            $table->foreign(['block_id', 'lesson_id'], 'lms_bookmarks_block')->references(['id', 'lesson_id'])->on('lms_lesson_blocks')->restrictOnDelete();
            $table->unsignedInteger('position_milliseconds');
            $table->string('label', 500)->nullable();
            $table->timestamps();
            $table->index(['student_id', 'lesson_id', 'id']);
        });
        DB::statement('ALTER TABLE lms_lesson_bookmarks ADD CONSTRAINT lms_bookmarks_position CHECK (position_milliseconds <= 86400000)');
    }

    public function down(): void
    {
        $tables = ['lms_lesson_bookmarks', 'lms_lesson_notes', 'lms_learning_visits'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Populated personal learning data requires reviewed recovery; destructive rollback refused.');
            }
        }
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('lms_lesson_blocks', function (Blueprint $table): void {
            $table->dropUnique('lms_blocks_lesson_parent');
        });
    }
};
