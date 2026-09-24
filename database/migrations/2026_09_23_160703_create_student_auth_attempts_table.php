<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_auth_attempts', function (Blueprint $table) {
            $table->string('fingerprint', 80)->primary();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->dateTime('cooldown_until')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('student_auth_attempts')->exists()) {
            throw new RuntimeException('Cannot drop active student-auth failure state during rollback.');
        }

        Schema::dropIfExists('student_auth_attempts');
    }
};
