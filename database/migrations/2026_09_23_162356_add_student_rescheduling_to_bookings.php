<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('admin_reconfirmation_needed')->default(false);
        });

        Schema::create('session_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->enum('actor_type', ['student', 'admin', 'system']);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->dateTime('old_start_at_utc');
            $table->dateTime('new_start_at_utc');
            $table->string('old_timezone', 64);
            $table->string('new_timezone', 64);
            $table->string('idempotency_key', 80)->unique();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('session_reschedules')->exists() || DB::table('bookings')->where('admin_reconfirmation_needed', true)->exists()) {
            throw new RuntimeException('Cannot discard student rescheduling history or pending reconfirmation.');
        }

        Schema::dropIfExists('session_reschedules');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('admin_reconfirmation_needed');
        });
    }
};
