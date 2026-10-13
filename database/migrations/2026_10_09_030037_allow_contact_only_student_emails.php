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
        Schema::table('student_emails', function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('student_emails')->whereNull('verified_at')->exists()) {
            throw new RuntimeException('Contact-only email addresses must be preserved. Resolve their migration explicitly before restoring a required verification timestamp.');
        }

        Schema::table('student_emails', function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable(false)->change();
        });
    }
};
