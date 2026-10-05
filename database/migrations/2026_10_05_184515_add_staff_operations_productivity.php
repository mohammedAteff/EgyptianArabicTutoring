<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff_bins', function (Blueprint $table): void {
            $table->boolean('pinned')->default(false);
            $table->foreignId('pinned_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamp('pinned_at')->nullable();
            $table->index(['deleted_at', 'pinned', 'updated_at']);
        });
        Schema::create('staff_note_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administrator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_bin_id')->constrained()->cascadeOnDelete();
            $table->boolean('pinned')->default(false);
            $table->boolean('favorite')->default(false);
            $table->timestamps();
            $table->unique(['administrator_id', 'staff_bin_id']);
            $table->index(['administrator_id', 'pinned']);
            $table->index(['administrator_id', 'favorite']);
        });
        Schema::create('student_operational_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('administrators')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('administrators')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 16)->default('active');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status', 'updated_at'], 'student_alerts_student_status_updated');
            $table->index(['status', 'updated_at']);
        });
        Schema::create('staff_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->constrained('administrators')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('administrators')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['assignee_id', 'status', 'due_date']);
            $table->index(['status', 'due_date']);
            $table->index(['created_by', 'status']);
        });
        Schema::create('staff_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administrator_id')->constrained()->cascadeOnDelete();
            $table->string('section', 32);
            $table->string('name', 80);
            $table->json('filters');
            $table->timestamps();
            $table->unique(['administrator_id', 'section', 'name']);
        });
        Schema::create('staff_recent_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administrator_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 24);
            $table->unsignedBigInteger('entity_id');
            $table->timestamp('viewed_at');
            $table->unique(['administrator_id', 'entity_type', 'entity_id'], 'staff_recent_views_identity');
            $table->index(['administrator_id', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_recent_views');
        Schema::dropIfExists('staff_saved_views');
        Schema::dropIfExists('staff_tasks');
        Schema::dropIfExists('student_operational_alerts');
        Schema::dropIfExists('staff_note_preferences');
        Schema::table('staff_bins', function (Blueprint $table): void {
            $table->dropIndex(['deleted_at', 'pinned', 'updated_at']);
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropColumn(['pinned', 'pinned_at']);
        });
    }
};
