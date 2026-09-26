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
        Schema::table('daily_country_metrics', function (Blueprint $table) {
            $table->unsignedInteger('bounced_sessions_count')->default(0)->after('page_views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_country_metrics', function (Blueprint $table) {
            $table->dropColumn('bounced_sessions_count');
        });
    }
};
