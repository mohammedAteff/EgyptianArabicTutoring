<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('session_types', function (Blueprint $table): void {
            $table->boolean('student_discoverable')->default(true);
        });
        Schema::table('resources', function (Blueprint $table): void {
            $table->boolean('student_discoverable')->default(true);
        });
        DB::table('session_types')->where('id', 3)->where('slug', 'feature-browser-qa')->update(['student_discoverable' => false]);
        DB::table('session_types')->where('id', 4)->where('slug', 'pass4-two-hour-qa')->update(['student_discoverable' => false]);
        DB::table('resources')->where('id', 1)->where('slug', 'synthetic-followup-access-guide')->update(['student_discoverable' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->dropColumn('student_discoverable');
        });
        Schema::table('session_types', function (Blueprint $table): void {
            $table->dropColumn('student_discoverable');
        });
    }
};
