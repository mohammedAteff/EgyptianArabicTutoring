<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_waitlists', function (Blueprint $table): void {
            $table->time('preferred_time_from')->nullable();
            $table->time('preferred_time_to')->nullable();
            $table->dateTime('last_offered_start_at')->nullable();
            $table->dateTime('availability_notified_at')->nullable();
        });
        Schema::table('student_packages', function (Blueprint $table): void {
            $table->dateTime('funding_activated_at')->nullable();
        });
        DB::table('student_packages')->whereNotNull('validity_days')->whereNotNull('validity_started_at')
            ->update(['funding_activated_at' => DB::raw('validity_started_at')]);
        Schema::create('student_birthday_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('birthday_date');
            $table->unsignedSmallInteger('days_before');
            $table->timestamp('created_at');
            $table->unique(['student_id', 'birthday_date', 'days_before'], 'birthday_reminder_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_birthday_reminders');
        Schema::table('student_packages', fn (Blueprint $table) => $table->dropColumn('funding_activated_at'));
        Schema::table('booking_waitlists', fn (Blueprint $table) => $table->dropColumn(['preferred_time_from', 'preferred_time_to', 'last_offered_start_at', 'availability_notified_at']));
    }
};
