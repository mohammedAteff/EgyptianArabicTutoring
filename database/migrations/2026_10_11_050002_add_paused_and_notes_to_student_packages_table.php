<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_packages', function (Blueprint $table): void {
            $table->timestamp('paused_at')->nullable()->after('expires_at');
            $table->string('pause_reason', 255)->nullable()->after('paused_at');
            $table->foreignId('paused_by')->nullable()->after('pause_reason')->constrained('administrators')->nullOnDelete();
            $table->text('admin_notes')->nullable()->after('paused_by');
        });
    }

    public function down(): void
    {
        Schema::table('student_packages', function (Blueprint $table): void {
            $table->dropForeign(['paused_by']);
            $table->dropColumn(['paused_at', 'pause_reason', 'paused_by', 'admin_notes']);
        });
    }
};
