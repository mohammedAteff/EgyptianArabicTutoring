<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('package_name');
            $table->decimal('original_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('total_sessions_allocated');
            $table->date('expiration_date')->nullable();
            $table->enum('status', ['active', 'completed', 'expired', 'cancelled'])->default('active');
            $table->timestamps();
            $table->index(['student_id', 'status', 'expiration_date']);
        });

        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_package_id')->constrained('student_packages')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('idempotency_key', 80)->unique();
            $table->decimal('amount_paid', 10, 2);
            $table->string('currency', 3);
            $table->string('payment_method')->default('PayPal - Manual');
            $table->string('transaction_reference')->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at');
            $table->index(['student_package_id', 'student_id']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_record_id')->constrained('payment_records')->restrictOnDelete();
            $table->foreignId('student_package_id')->constrained('student_packages')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('idempotency_key', 80)->unique();
            $table->decimal('amount_refunded', 10, 2);
            $table->string('currency', 3);
            $table->text('reason')->nullable();
            $table->dateTime('refunded_at');
            $table->foreignId('recorded_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['student_package_id', 'student_id']);
        });

        Schema::create('session_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('student_package_id')->nullable()->constrained('student_packages')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->string('idempotency_key', 80)->unique();
            $table->enum('entry_type', ['package_grant', 'session_consumed', 'courtesy_adjustment', 'cancellation_restore', 'expiration_forfeit']);
            $table->integer('credit_change');
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['booking_id', 'entry_type']);
            $table->index(['student_package_id', 'student_id']);
        });
    }

    public function down(): void
    {
        foreach (['session_ledger_entries', 'payment_refunds', 'payment_records', 'student_packages'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Cannot drop populated student billing tables.');
            }
        }

        Schema::dropIfExists('session_ledger_entries');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_records');
        Schema::dropIfExists('student_packages');
    }
};
