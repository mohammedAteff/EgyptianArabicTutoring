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
        Schema::table('student_bins', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(1);
            $table->uuid('quick_note_key')->nullable();
            $table->unique(['student_id', 'quick_note_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_bins', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'quick_note_key']);
            $table->dropColumn(['revision', 'quick_note_key']);
        });
    }
};
