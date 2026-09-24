<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_booking_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->string('reason', 64);
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_booking_timezone_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->string('reason', 64);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('migration_booking_exceptions')->exists() || DB::table('migration_booking_timezone_exceptions')->exists()) {
            throw new RuntimeException('Cannot drop populated student backfill exception ledgers.');
        }

        Schema::dropIfExists('migration_booking_timezone_exceptions');
        Schema::dropIfExists('migration_booking_exceptions');
    }
};
