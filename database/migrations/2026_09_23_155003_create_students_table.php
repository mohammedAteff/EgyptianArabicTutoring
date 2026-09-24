<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('name_normalized')->index();
            $table->string('email')->nullable()->index();
            $table->string('email_normalized')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('phone_normalized')->nullable()->index();
            $table->date('date_of_birth')->nullable();
            $table->string('preferred_timezone', 64)->nullable();
            $table->enum('identity_status', ['verified', 'legacy_unverified', 'merged'])->default('legacy_unverified')->index();
            $table->foreignId('possible_duplicate_of_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('merged_into_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (DB::table('students')->exists() || DB::table('bookings')->whereNotNull('student_id')->exists()) {
            throw new RuntimeException('Cannot drop populated students or linked bookings.');
        }

        Schema::dropIfExists('students');
    }
};
