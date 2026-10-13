<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_pinned_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administrator_id')->constrained('administrators')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['administrator_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_pinned_students');
    }
};
