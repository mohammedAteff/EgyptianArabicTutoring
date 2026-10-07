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
        Schema::create('lms_courses', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 160)->unique();
            $table->enum('kind', ['catalog', 'private'])->default('catalog');
            $table->foreignId('owner_student_id')->nullable()->constrained('students')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $this->publication($table);
            $table->timestamps();
            $table->index(['owner_student_id', 'status', 'id'], 'lms_courses_owner');
        });
        DB::statement("ALTER TABLE lms_courses ADD CONSTRAINT lms_course_owner CHECK ((kind = 'catalog' AND owner_student_id IS NULL) OR (kind = 'private' AND owner_student_id IS NOT NULL))");
        Schema::create('lms_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $table->string('title', 200);
            $table->unsignedInteger('sort_order')->default(0);
            $this->publication($table);
            $table->timestamps();
            $table->unique(['id', 'course_id'], 'lms_sections_parent');
            $table->index(['course_id', 'sort_order', 'id'], 'lms_sections_order');
        });
        Schema::create('lms_lessons', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('course_id');
            $table->foreign(['section_id', 'course_id'], 'lms_lessons_section')->references(['id', 'course_id'])->on('lms_sections')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('slug', 160);
            $table->unsignedInteger('sort_order')->default(0);
            $this->publication($table);
            $table->timestamps();
            $table->unique(['id', 'course_id'], 'lms_lessons_parent');
            $table->unique(['course_id', 'slug'], 'lms_lessons_slug');
            $table->index(['section_id', 'sort_order', 'id'], 'lms_lessons_order');
        });
        Schema::create('lms_lesson_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lms_lessons')->restrictOnDelete();
            $table->string('kind', 24);
            $table->enum('status', ['placeholder', 'ready', 'withdrawn'])->default('placeholder');
            $table->foreignId('resource_id')->nullable()->constrained('resources')->restrictOnDelete();
            $table->json('payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['lesson_id', 'status', 'sort_order', 'id'], 'lms_blocks_order');
        });
        DB::statement("ALTER TABLE lms_lesson_blocks ADD CONSTRAINT lms_block_payload CHECK (
            kind IN ('bunny_video','youtube_video','external_video','rich_text','image','file','resource','external_link','quiz','assignment')
            AND ((status = 'placeholder' AND resource_id IS NULL AND payload IS NULL)
                OR (status = 'withdrawn' AND resource_id IS NULL AND payload IS NULL)
                OR (status = 'ready' AND ((kind = 'resource' AND resource_id IS NOT NULL AND payload IS NULL)
                    OR (kind IN ('rich_text','external_link','youtube_video','external_video') AND resource_id IS NULL AND payload IS NOT NULL)))))");
        Schema::create('lms_access_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained('lms_courses')->restrictOnDelete();
            $table->enum('audience', ['public', 'member', 'all_students', 'selected_students']);
            $this->window($table, true);
            $table->foreignId('updated_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('lms_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $this->target($table, 'lms_grants');
            $table->string('source_kind', 40);
            $table->string('source_key', 160);
            $table->string('issuance_fingerprint', 64);
            $table->foreignId('granted_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->foreignId('regranted_from_id')->nullable()->constrained('lms_access_grants')->restrictOnDelete();
            $this->window($table);
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->dateTime('revoked_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['source_kind', 'source_key', 'scope_key'], 'lms_grants_source');
            $table->index(['student_id', 'course_id', 'status', 'expires_at'], 'lms_grants_access');
            $table->unique(['id', 'student_id', 'course_id'], 'lms_grants_owner');
        });
        DB::statement("ALTER TABLE lms_access_grants ADD CONSTRAINT lms_grant_revocation CHECK ((status = 'active' AND revoked_at IS NULL) OR (status = 'revoked' AND revoked_at IS NOT NULL))");
        DB::statement('ALTER TABLE lms_access_grants ADD CONSTRAINT lms_grant_scope CHECK (section_id IS NULL OR lesson_id IS NULL)');
        Schema::create('lms_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $table->enum('status', ['enrolled', 'archived', 'superseded'])->default('enrolled');
            $table->foreignId('superseded_by_id')->nullable()->constrained('lms_enrollments')->restrictOnDelete();
            $table->unsignedBigInteger('live_course_key')->virtualAs("CASE WHEN status <> 'superseded' THEN course_id ELSE NULL END");
            $table->dateTime('enrolled_at');
            $table->foreignId('enrolled_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'live_course_key'], 'lms_enrollment_live');
            $table->index(['course_id', 'status', 'student_id'], 'lms_enrollment_students');
        });
        Schema::create('lms_learning_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('access_grant_id')->unique()->constrained('lms_access_grants')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->text('instructions')->nullable();
            $table->enum('status', ['assigned', 'withdrawn'])->default('assigned');
            $table->timestamps();
            $table->index(['booking_id', 'status'], 'lms_assignment_booking');
        });
        Schema::create('lms_access_events', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_key', 64)->unique();
            $table->string('fingerprint', 64);
            $table->string('action', 40);
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $table->foreignId('access_grant_id')->nullable()->constrained('lms_access_grants')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('lms_enrollments')->restrictOnDelete();
            $table->foreignId('learning_assignment_id')->nullable()->constrained('lms_learning_assignments')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->json('changes');
            $table->dateTime('created_at');
            $table->index(['student_id', 'course_id', 'created_at', 'id'], 'lms_access_history');
        });
        foreach (['lms_courses', 'lms_sections', 'lms_lessons'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_publication CHECK (status <> 'published' OR published_at IS NOT NULL)");
        }
        foreach (['lms_access_rules', 'lms_access_grants'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_window CHECK (
                (access_mode = 'permanent' AND expires_at IS NULL AND relative_days IS NULL)
                OR (access_mode = 'fixed' AND starts_at IS NOT NULL AND expires_at IS NOT NULL AND expires_at > starts_at AND relative_days IS NULL)
                OR (access_mode = 'relative' AND relative_days IS NOT NULL AND relative_days > 0 AND ".($table === 'lms_access_grants' ? 'expires_at IS NOT NULL AND expires_at > starts_at' : 'expires_at IS NULL').'))');
        }
    }

    private function publication(Blueprint $table): void
    {
        $table->enum('status', ['draft', 'published', 'unpublished', 'archived'])->default('draft');
        $table->dateTime('published_at')->nullable();
        $table->unsignedInteger('lock_version')->default(1);
    }

    private function window(Blueprint $table, bool $rule = false): void
    {
        $table->enum('access_mode', ['permanent', 'fixed', 'relative'])->default('permanent');
        $column = $table->dateTime('starts_at');
        if ($rule) {
            $column->nullable();
        }
        $table->dateTime('expires_at')->nullable();
        $table->unsignedInteger('relative_days')->nullable();
    }

    private function target(Blueprint $table, string $prefix): void
    {
        $table->unsignedBigInteger('section_id')->nullable();
        $table->unsignedBigInteger('lesson_id')->nullable();
        $table->foreign(['section_id', 'course_id'], $prefix.'_section')->references(['id', 'course_id'])->on('lms_sections')->restrictOnDelete();
        $table->foreign(['lesson_id', 'course_id'], $prefix.'_lesson')->references(['id', 'course_id'])->on('lms_lessons')->restrictOnDelete();
        $table->string('scope_key', 64)->virtualAs("CASE WHEN lesson_id IS NOT NULL THEN CONCAT('lesson:', lesson_id) WHEN section_id IS NOT NULL THEN CONCAT('section:', section_id) ELSE 'course' END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['lms_access_events', 'lms_learning_assignments', 'lms_enrollments', 'lms_access_grants', 'lms_access_rules', 'lms_lesson_blocks', 'lms_lessons', 'lms_sections', 'lms_courses'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Populated LMS history requires reviewed recovery; destructive rollback refused.');
            }
        }
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
