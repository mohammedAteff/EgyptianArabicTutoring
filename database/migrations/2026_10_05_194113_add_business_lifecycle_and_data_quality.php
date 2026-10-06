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
        if (DB::table('availability_exceptions')->select('date')->groupBy('date')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate calendar exception dates require review before this migration.');
        }
        Schema::table('availability_exceptions', function (Blueprint $table): void {
            $table->unique('date', 'availability_exceptions_date_unique');
        });
        Schema::table('students', function (Blueprint $table): void {
            $table->string('operational_status', 16)->default('active')->index();
            $table->timestamp('operational_status_changed_at')->nullable();
            $table->string('operational_reason_code', 40)->nullable();
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->json('policy_snapshot')->nullable();
        });
        Schema::create('recurring_lesson_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('session_type_id')->constrained()->restrictOnDelete();
            $table->string('cadence', 16);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('occurrence_count')->nullable();
            $table->time('preferred_time');
            $table->string('timezone', 64);
            $table->string('fold_policy', 8)->default('reject');
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->char('fingerprint', 64);
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });
        Schema::create('recurring_lesson_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recurring_lesson_plan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('local_date');
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('reason_code', 40)->nullable();
            $table->timestamps();
            $table->unique(['recurring_lesson_plan_id', 'sequence'], 'recurring_plan_sequence_unique');
        });
        Schema::create('booking_policy_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('action', 20);
            $table->string('outcome', 16);
            $table->string('reason_code', 40)->nullable();
            $table->json('policy_snapshot');
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at');
            $table->unique(['booking_id', 'action']);
        });
        Schema::create('booking_waitlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('session_type_id')->constrained()->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('timezone', 64);
            $table->string('status', 16)->default('open');
            $table->string('reason_code', 40)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->char('fingerprint', 64);
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index(['status', 'date_from']);
        });
        Schema::create('student_unavailabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->string('timezone', 64);
            $table->string('reason_code', 40)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('status', 16)->default('active');
            $table->string('idempotency_key', 100)->unique();
            $table->char('fingerprint', 64);
            $table->timestamps();
            $table->index(['student_id', 'status', 'starts_at_utc'], 'student_unavailability_lookup');
        });
        Schema::create('package_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_package_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('expected_amount', 10, 2);
            $table->date('due_date');
            $table->string('status', 16)->default('scheduled');
            $table->string('schedule_key', 100);
            $table->char('fingerprint', 64);
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_package_id', 'sequence']);
            $table->unique(['schedule_key', 'sequence']);
            $table->index(['status', 'due_date']);
        });
        Schema::create('package_renewals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_package_id')->constrained('student_packages')->restrictOnDelete();
            $table->foreignId('new_package_id')->unique()->constrained('student_packages')->restrictOnDelete();
            $table->date('renewal_date');
            $table->string('reason_code', 40)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->char('fingerprint', 64);
            $table->timestamp('created_at');
            $table->index(['student_id', 'renewal_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['package_renewals', 'package_installments', 'booking_policy_decisions', 'recurring_lesson_occurrences', 'recurring_lesson_plans', 'booking_waitlists', 'student_unavailabilities'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Lifecycle history requires reviewed forward recovery, not a destructive rollback.');
            }
        }
        foreach (['package_renewals', 'package_installments', 'student_unavailabilities', 'booking_waitlists', 'booking_policy_decisions', 'recurring_lesson_occurrences', 'recurring_lesson_plans'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('policy_snapshot'));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['operational_status', 'operational_status_changed_at', 'operational_reason_code']));
        Schema::table('availability_exceptions', fn (Blueprint $table) => $table->dropUnique('availability_exceptions_date_unique'));
    }
};
