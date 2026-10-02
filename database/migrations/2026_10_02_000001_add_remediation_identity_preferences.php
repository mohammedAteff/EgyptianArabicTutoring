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
        if (! Schema::hasColumn('administrators', 'time_format')) {
            Schema::table('administrators', function (Blueprint $table): void {
                $table->string('time_format', 2)->default('24');
            });
        }
        if (! Schema::hasColumn('visitors', 'student_id')) {
            Schema::table('visitors', function (Blueprint $table): void {
                $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Identity links and staff preferences require a reviewed forward migration.');
    }
};
