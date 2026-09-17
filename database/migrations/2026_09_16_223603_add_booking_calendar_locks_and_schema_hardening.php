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
        // 1. Canonical calendar lock rows for deterministic row-level serialization
        if (! Schema::hasTable('booking_calendar_locks')) {
            Schema::create('booking_calendar_locks', function (Blueprint $table) {
                $table->id();
                $table->date('lock_date')->unique();
                $table->timestamps();
            });
        }

        // 2. Add is_gated to resources if missing
        if (Schema::hasTable('resources') && ! Schema::hasColumn('resources', 'is_gated')) {
            Schema::table('resources', function (Blueprint $table) {
                $table->boolean('is_gated')->default(true)->after('cover_image_path');
            });
        }

        // 3. Add cryptographically secure hold_token to booking_holds
        if (Schema::hasTable('booking_holds') && ! Schema::hasColumn('booking_holds', 'hold_token')) {
            Schema::table('booking_holds', function (Blueprint $table) {
                $table->string('hold_token', 64)->nullable()->index()->after('session_token');
            });
        }

        // 4. Harden contacts.email uniqueness
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                try {
                    $table->dropIndex(['email']);
                } catch (Throwable $e) {
                    // Index may have a different name or already dropped
                }
                $table->unique('email', 'contacts_email_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropUnique('contacts_email_unique');
                $table->index('email');
            });
        }

        if (Schema::hasTable('booking_holds') && Schema::hasColumn('booking_holds', 'hold_token')) {
            Schema::table('booking_holds', function (Blueprint $table) {
                $table->dropColumn('hold_token');
            });
        }

        if (Schema::hasTable('resources') && Schema::hasColumn('resources', 'is_gated')) {
            Schema::table('resources', function (Blueprint $table) {
                $table->dropColumn('is_gated');
            });
        }

        Schema::dropIfExists('booking_calendar_locks');
    }
};
