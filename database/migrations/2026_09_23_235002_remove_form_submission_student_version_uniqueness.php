<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->index(['form_version_id', 'student_id'], 'form_submissions_version_student_index');
        });

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropUnique(['form_version_id', 'student_id']);
        });
    }

    public function down(): void
    {
        $hasDuplicates = DB::table('form_submissions')
            ->select(['form_version_id', 'student_id'])
            ->groupBy('form_version_id', 'student_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Cannot restore form submission uniqueness while merged students have multiple preserved submissions for a version.');
        }

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->unique(['form_version_id', 'student_id']);
        });

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropIndex('form_submissions_version_student_index');
        });
    }
};
