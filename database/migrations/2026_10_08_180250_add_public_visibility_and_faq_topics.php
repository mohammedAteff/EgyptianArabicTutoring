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
        Schema::table('session_types', function (Blueprint $table): void {
            $table->boolean('public_bookable')->default(true);
        });
        Schema::table('resources', function (Blueprint $table): void {
            $table->boolean('public_catalogue')->default(true);
        });
        Schema::table('faqs', function (Blueprint $table): void {
            $table->string('topic', 100)->nullable();
            $table->boolean('featured')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table): void {
            $table->dropColumn(['topic', 'featured']);
        });
        Schema::table('resources', function (Blueprint $table): void {
            $table->dropColumn('public_catalogue');
        });
        Schema::table('session_types', function (Blueprint $table): void {
            $table->dropColumn('public_bookable');
        });
    }
};
