<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_used_step')->nullable();
            $table->uuid('two_factor_version')->nullable();
            $table->text('two_factor_pending_secret')->nullable();
            $table->timestamp('two_factor_pending_at')->nullable();
            $table->char('two_factor_pending_session', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('students')->whereNotNull('two_factor_secret')->exists()) {
            throw new RuntimeException('Cannot remove enrolled student authenticators.');
        }
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_used_step', 'two_factor_version', 'two_factor_pending_secret', 'two_factor_pending_at', 'two_factor_pending_session']));
    }
};
