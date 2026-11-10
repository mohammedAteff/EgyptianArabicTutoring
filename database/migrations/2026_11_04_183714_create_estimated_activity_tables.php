<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimated_activity_context_versions', function (Blueprint $table): void {
            $table->foreignId('course_id')->primary()->constrained('lms_courses')->cascadeOnDelete();
            $table->unsignedBigInteger('generation')->default(1);
        });
        Schema::create('estimated_activity_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('runtime_hash', 64);
            $table->unsignedInteger('generation')->default(1);
            $table->boolean('enabled')->default(false);
            $table->dateTime('expires_at', 6);
            $table->dateTime('created_at', 6);
        });
        Schema::create('estimated_activity_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('estimated_activity_runs')->restrictOnDelete();
            $table->string('principal_type', 12);
            $table->unsignedBigInteger('principal_id');
            $table->string('principal_fingerprint', 64);
            $table->dateTime('expires_at', 6);
            $table->dateTime('revoked_at', 6)->nullable();
            $table->unique(['run_id', 'principal_type', 'principal_id'], 'estimated_grant_principal_unique');
            $table->index(['principal_type', 'principal_id']);
        });
        Schema::create('estimated_activity_streams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('grant_id')->constrained('estimated_activity_grants')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('session_hash', 64);
            $table->uuid('client_stream');
            $table->unsignedInteger('generation');
            $table->string('definition', 40);
            $table->string('kind', 40);
            $table->unsignedBigInteger('record_id');
            $table->string('category', 40);
            $table->string('context_hash', 64);
            $table->unsignedInteger('sequence')->default(0);
            $table->dateTime('received_at', 6);
            $table->dateTime('expires_at', 6);
            $table->index(['grant_id', 'received_at']);
            $table->index('expires_at');
        });
        Schema::create('estimated_activity_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('stream_id')->constrained('estimated_activity_streams')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('fingerprint', 64);
            $table->unsignedTinyInteger('seconds');
            $table->dateTime('received_at', 6);
            $table->unique(['stream_id', 'sequence'], 'estimated_receipt_sequence_unique');
        });
        foreach (['estimated_activity_intervals', 'estimated_activity_coverage'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->foreignUuid('run_id')->constrained('estimated_activity_runs')->restrictOnDelete();
                $table->uuid('grant_id');
                $table->uuid('stream_id');
                $table->foreignId('student_id')->nullable()->constrained('students')->restrictOnDelete();
                $table->foreignId('administrator_id')->nullable()->constrained('administrators')->nullOnDelete();
                $table->foreignId('workspace_id')->nullable()->constrained('tutor_work_workspaces')->restrictOnDelete();
                $table->unsignedInteger('generation');
                $table->string('definition', 40);
                $table->string('kind', 40);
                $table->unsignedBigInteger('record_id');
                $table->string('category', 40);
                if ($name === 'estimated_activity_intervals') {
                    $table->unsignedInteger('sequence');
                    $table->dateTime('started_at', 6);
                    $table->dateTime('ended_at', 6);
                    $table->unique(['stream_id', 'sequence'], 'estimated_interval_sequence_unique');
                    $table->index(['student_id', 'ended_at', 'started_at'], 'estimated_student_period_index');
                    $table->index(['workspace_id', 'ended_at', 'started_at'], 'estimated_workspace_period_index');
                } else {
                    $table->dateTime('anchored_at', 6);
                    $table->dateTime('last_accepted_at', 6)->nullable();
                    $table->dateTime('ended_at', 6)->nullable();
                    $table->string('end_reason', 24)->nullable();
                    $table->unique('stream_id', 'estimated_coverage_stream_unique');
                    $table->index(['student_id', 'anchored_at'], 'estimated_student_coverage_index');
                    $table->index(['workspace_id', 'anchored_at'], 'estimated_workspace_coverage_index');
                }
            });
        }
        DB::statement('ALTER TABLE estimated_activity_intervals ADD CONSTRAINT estimated_interval_positive CHECK (ended_at > started_at), ADD CONSTRAINT estimated_interval_scope CHECK ((student_id IS NOT NULL AND workspace_id IS NULL) OR (student_id IS NULL AND workspace_id IS NOT NULL))');
    }

    public function down(): void
    {
        foreach (['estimated_activity_coverage', 'estimated_activity_intervals', 'estimated_activity_receipts', 'estimated_activity_streams', 'estimated_activity_grants', 'estimated_activity_runs', 'estimated_activity_context_versions'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
