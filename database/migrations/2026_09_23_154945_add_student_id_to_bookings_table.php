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
            $table->foreignId('student_id')->nullable()->after('contact_id')->index();
        });
    }

    public function down(): void
    {
        if (DB::table('bookings')->whereNotNull('student_id')->exists()) {
            throw new RuntimeException('Cannot remove bookings.student_id while linked bookings exist.');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('student_id');
        });
    }
};
