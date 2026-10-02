<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_holds', function (Blueprint $table): void {
            $table->text('lead_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_holds', function (Blueprint $table): void {
            $table->dropColumn('lead_details');
        });
    }
};
