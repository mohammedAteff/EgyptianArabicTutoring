<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_bins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained('administrators')->restrictOnDelete();
            $table->string('title', 160);
            $table->mediumText('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['deleted_at', 'updated_at']);
        });
        Schema::create('student_bins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('created_by_type', 10);
            $table->unsignedBigInteger('created_by_id');
            $table->boolean('student_visible')->default(true);
            $table->string('title', 160);
            $table->mediumText('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'deleted_at', 'student_visible']);
        });
    }

    public function down(): void
    {
        if (DB::table('staff_bins')->exists() || DB::table('student_bins')->exists()) {
            throw new RuntimeException('Populated educational and staff notes require a reviewed forward migration.');
        }
        Schema::dropIfExists('student_bins');
        Schema::dropIfExists('staff_bins');
    }
};
