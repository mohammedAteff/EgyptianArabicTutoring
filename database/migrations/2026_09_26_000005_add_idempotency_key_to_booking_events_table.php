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
        Schema::table('booking_events', function (Blueprint $table) {
            $table->string('idempotency_key', 120)->nullable()->after('event_type');
            $table->unique('idempotency_key', 'booking_events_idempotency_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_events', function (Blueprint $table) {
            $table->dropUnique('booking_events_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
