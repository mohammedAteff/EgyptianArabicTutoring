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
        Schema::table('lms_video_assets', function (Blueprint $table) {
            $table->index(['provider', 'status', 'id'], 'lms_video_operations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_video_assets', function (Blueprint $table) {
            $table->dropIndex('lms_video_operations');
        });
    }
};
