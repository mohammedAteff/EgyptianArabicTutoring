<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('subject', 160);
            $table->string('type', 24);
            $table->string('area', 24);
            $table->string('status', 24)->default('open');
            $table->string('context_type', 16)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamp('last_activity_at', 6);
            $table->timestamp('erased_at')->nullable();
            $table->timestamps(6);
            $table->index(['student_id', 'last_activity_at', 'id'], 'feedback_student_activity');
            $table->index(['erased_at', 'status', 'last_activity_at'], 'feedback_inbox');
        });
        Schema::create('feedback_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('feedback_conversations')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('kind', 16);
            $table->text('body')->nullable();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->timestamp('created_at', 6);
            $table->unique(['conversation_id', 'sequence']);
        });
        Schema::create('feedback_read_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('feedback_conversations')->restrictOnDelete();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->unique(['conversation_id', 'actor_type', 'actor_id'], 'feedback_reader');
        });
        Schema::create('feedback_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained('feedback_conversations')->restrictOnDelete();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('scope_id');
            $table->uuid('request_key');
            $table->string('action', 16);
            $table->char('fingerprint', 64);
            $table->unsignedInteger('result_sequence');
            $table->unsignedInteger('result_version');
            $table->timestamp('created_at', 6);
            $table->unique(['actor_type', 'actor_id', 'scope_id', 'request_key'], 'feedback_operation_identity');
            $table->index(['student_id', 'actor_type', 'action', 'created_at'], 'feedback_student_quota');
        });
        Schema::table('admin_notifications', function (Blueprint $table): void {
            $table->char('feedback_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        throw new LogicException('Feedback history requires reviewed forward recovery; destructive rollback refused.');
    }
};
