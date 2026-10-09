<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_packages', function (Blueprint $table): void {
            $table->dateTime('validity_started_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->index(['student_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('student_packages', function (Blueprint $table): void {
            $table->dropIndex(['student_id', 'status', 'expires_at']);
            $table->dropColumn(['validity_started_at', 'expires_at']);
        });
    }
};
