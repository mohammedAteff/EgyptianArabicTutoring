<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->foreignId('published_version_id')->nullable()->after('active_version_id')->constrained('form_versions')->nullOnDelete();
        });

        DB::table('forms')
            ->where('status', 'published')
            ->whereNotNull('active_version_id')
            ->update(['published_version_id' => DB::raw('active_version_id')]);
    }

    public function down(): void
    {
        $hasUnpublishedActiveVersion = DB::table('forms')
            ->whereNotNull('published_version_id')
            ->whereNotNull('active_version_id')
            ->whereColumn('published_version_id', '!=', 'active_version_id')
            ->exists();

        if ($hasUnpublishedActiveVersion) {
            throw new RuntimeException('Cannot remove published-version tracking while an unpublished form version is active.');
        }

        DB::table('forms')
            ->whereNotNull('published_version_id')
            ->update(['active_version_id' => DB::raw('published_version_id')]);

        Schema::table('forms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_version_id');
        });
    }
};
