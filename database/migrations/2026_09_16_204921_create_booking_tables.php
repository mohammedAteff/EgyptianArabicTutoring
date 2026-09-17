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
        Schema::create('booking_holds', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_token', 64)->index();
            $table->string('session_token', 64)->nullable()->index();
            $table->foreignId('session_type_id')->constrained('session_types');
            $table->dateTime('slot_start_utc')->index();
            $table->dateTime('slot_end_utc')->index();
            $table->dateTime('expires_at')->index();
            $table->dateTime('released_at')->nullable();
            $table->string('status', 32)->default('active')->index(); // 'active', 'expired', 'converted', 'released'
            $table->timestamps();

            $table->index(['slot_start_utc', 'slot_end_utc', 'status', 'expires_at'], 'booking_holds_slot_lookup_index');
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts');
            $table->foreignId('session_type_id')->constrained('session_types');
            $table->dateTime('start_at_utc')->index();
            $table->dateTime('end_at_utc')->index();
            $table->string('business_timezone', 64);
            $table->string('customer_timezone', 64);
            $table->date('business_local_date_at_booking');
            $table->time('business_local_start_time_at_booking');
            $table->time('business_local_end_time_at_booking');
            $table->date('customer_local_date_at_booking');
            $table->time('customer_local_start_time_at_booking');
            $table->time('customer_local_end_time_at_booking');
            $table->string('business_utc_offset_at_booking', 10);
            $table->string('customer_utc_offset_at_booking', 10);
            $table->string('status', 32)->default('confirmed')->index(); // 'confirmed', 'cancelled', 'completed', 'no_show', 'pending'
            $table->string('idempotency_key', 100)->unique();
            $table->string('confirmation_token', 64)->unique();
            $table->text('notes')->nullable();
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('content')->nullable();
            $table->string('term')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'cancelled_at', 'start_at_utc', 'end_at_utc'], 'bookings_availability_check_index');
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('event_type', 32)->index(); // 'created', 'rescheduled', 'cancelled', 'confirmed', 'completed', 'marked_no_show', 'restored'
            $table->string('performed_by', 32); // 'customer', 'admin', 'system'
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->json('previous_data')->nullable();
            $table->json('new_data')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_holds');
    }
};
