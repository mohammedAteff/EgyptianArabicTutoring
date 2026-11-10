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
        Schema::create('tutor_work_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('transition_state', 16);
            $table->dateTime('cutover_at')->nullable();
            $table->json('transition_decision')->nullable();
            $table->string('preservation_digest', 64)->nullable();
        });
        DB::table('tutor_work_workspaces')->insert(['key' => 'sole-tutor', 'transition_state' => 'schema_pending']);
        foreach (['tutor_work_intervals', 'tutor_work_revisions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['entry_id', 'administrator_id']);
                $table->unsignedBigInteger('administrator_id')->nullable()->change();
            });
        }
        Schema::table('tutor_work_entries', function (Blueprint $table): void {
            $table->dropForeign(['administrator_id']);
            $table->unsignedBigInteger('administrator_id')->nullable()->change();
            $table->foreign('administrator_id')->references('id')->on('administrators')->restrictOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('tutor_work_workspaces')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->unsignedBigInteger('active_workspace')->nullable()->unique();
            $table->unique(['id', 'workspace_id']);
            $table->index(['workspace_id', 'work_date', 'id']);
            $table->index(['workspace_id', 'acceptance', 'lifecycle']);
        });
        DB::statement("ALTER TABLE tutor_work_entries DROP CONSTRAINT tutor_work_active_check, ADD CONSTRAINT tutor_work_scope_active_check CHECK ((workspace_id IS NULL AND active_workspace IS NULL AND ((origin = 'timer' AND lifecycle IN ('running','paused') AND active_owner IS NOT NULL AND active_owner = administrator_id) OR (lifecycle = 'stopped' AND active_owner IS NULL))) OR (workspace_id IS NOT NULL AND active_owner IS NULL AND ((origin = 'timer' AND lifecycle IN ('running','paused') AND active_workspace IS NOT NULL AND active_workspace = workspace_id) OR (lifecycle = 'stopped' AND active_workspace IS NULL))))");
        Schema::table('tutor_work_segments', function (Blueprint $table): void {
            $table->foreignId('opened_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('administrators')->nullOnDelete();
        });
        foreach (['tutor_work_intervals', 'tutor_work_revisions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreign('entry_id')->references('id')->on('tutor_work_entries')->restrictOnDelete();
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->foreign(['entry_id', 'workspace_id'])->references(['id', 'workspace_id'])->on('tutor_work_entries')->restrictOnDelete();
            });
        }
        Schema::table('tutor_work_intervals', function (Blueprint $table): void {
            $table->index(['workspace_id', 'ended_at', 'started_at']);
        });
        Schema::table('tutor_work_revisions', function (Blueprint $table): void {
            $table->foreignId('actor_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->foreignId('subject_administrator_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->boolean('system_action')->default(false);
            $table->index(['workspace_id', 'id']);
        });
        Schema::table('tutor_work_operations', function (Blueprint $table): void {
            $table->dropForeign(['administrator_id']);
            $table->unsignedBigInteger('administrator_id')->nullable()->change();
            $table->foreign('administrator_id')->references('id')->on('administrators')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('tutor_work_workspaces')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->unique(['workspace_id', 'actor_id', 'operation_id'], 'tutor_work_workspace_actor_operation_unique');
        });
        DB::table('tutor_work_workspaces')->where('key', 'sole-tutor')->update(['transition_state' => DB::table('tutor_work_entries')->exists() ? 'pending' : 'ready']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Shared Tutor Work is a forward-only transition. Restore a verified pre-cutover backup to recover separate legacy ledgers; shared actor history cannot be split by rollback.');
    }
};
