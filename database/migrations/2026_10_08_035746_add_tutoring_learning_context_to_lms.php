<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lms_courses', function (Blueprint $table): void {
            $table->char('private_learning_key', 64)->nullable()->unique();
            $table->char('private_learning_fingerprint', 64)->nullable();
        });
        Schema::table('lms_learning_assignments', function (Blueprint $table): void {
            $table->foreignId('lesson_block_id')->nullable()->constrained('lms_lesson_blocks')->restrictOnDelete();
            $table->string('block_kind', 20)->nullable();
        });
        Schema::table('lms_quiz_attempts', function (Blueprint $table): void {
            $table->timestamp('graded_at')->nullable();
        });
        Schema::table('lms_assignment_submissions', function (Blueprint $table): void {
            $table->timestamp('reviewed_at')->nullable();
        });
        DB::statement("ALTER TABLE lms_courses ADD CONSTRAINT lms_private_learning_context CHECK ((private_learning_key IS NULL AND private_learning_fingerprint IS NULL) OR (private_learning_key IS NOT NULL AND private_learning_fingerprint IS NOT NULL AND kind = 'private'))");
        DB::statement("ALTER TABLE lms_learning_assignments ADD CONSTRAINT lms_assignment_block_context CHECK ((lesson_block_id IS NULL AND block_kind IS NULL) OR (lesson_block_id IS NOT NULL AND block_kind IS NOT NULL AND block_kind IN ('quiz','assignment')))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Tutoring learning context retains assignment history; use a reviewed forward fix.');
    }
};
