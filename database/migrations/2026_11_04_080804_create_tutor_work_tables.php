<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_work_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administrator_id')->constrained('administrators')->restrictOnDelete();
            $table->unsignedBigInteger('active_owner')->nullable()->unique();
            $table->string('origin', 12);
            $table->string('placement', 12);
            $table->string('lifecycle', 12);
            $table->string('acceptance', 16);
            $table->unsignedInteger('version')->default(1);
            $table->string('category', 40);
            $table->text('description')->nullable();
            $table->string('timezone', 64);
            $table->date('work_date');
            $table->unsignedBigInteger('unplaced_seconds')->nullable();
            $table->json('original_declaration');
            $table->string('system_reason')->nullable();
            $table->boolean('corrected')->default(false);
            $table->timestamps();
            $table->unique(['id', 'administrator_id']);
            $table->index(['administrator_id', 'work_date', 'id']);
            $table->index(['administrator_id', 'acceptance', 'lifecycle']);
        });
        DB::statement("ALTER TABLE tutor_work_entries ADD CONSTRAINT tutor_work_active_check CHECK ((origin = 'timer' AND lifecycle IN ('running','paused') AND active_owner IS NOT NULL AND active_owner = administrator_id) OR (lifecycle = 'stopped' AND active_owner IS NULL))");
        Schema::create('tutor_work_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained('tutor_work_entries')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedBigInteger('open_entry')->nullable()->unique();
            $table->index(['entry_id', 'started_at']);
        });
        DB::statement('ALTER TABLE tutor_work_segments ADD CONSTRAINT tutor_segment_open_check CHECK ((ended_at IS NULL AND open_entry IS NOT NULL AND open_entry = entry_id) OR (ended_at IS NOT NULL AND open_entry IS NULL AND ended_at >= started_at))');
        Schema::create('tutor_work_intervals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('administrator_id');
            $table->foreign(['entry_id', 'administrator_id'])->references(['id', 'administrator_id'])->on('tutor_work_entries')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->index(['administrator_id', 'ended_at', 'started_at']);
        });
        DB::statement('ALTER TABLE tutor_work_intervals ADD CONSTRAINT tutor_interval_positive CHECK (ended_at > started_at)');
        Schema::create('tutor_work_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('administrator_id');
            $table->foreign(['entry_id', 'administrator_id'])->references(['id', 'administrator_id'])->on('tutor_work_entries')->restrictOnDelete();
            $table->unsignedInteger('from_version');
            $table->unsignedInteger('to_version');
            $table->string('action', 32);
            $table->text('reason')->nullable();
            $table->json('before_value');
            $table->json('after_value');
            $table->dateTime('created_at');
            $table->unique(['entry_id', 'to_version']);
        });
        Schema::create('tutor_work_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administrator_id')->constrained('administrators')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('fingerprint', 64);
            $table->json('result');
            $table->dateTime('created_at');
            $table->unique(['administrator_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        foreach (['tutor_work_operations', 'tutor_work_revisions', 'tutor_work_intervals', 'tutor_work_segments', 'tutor_work_entries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
