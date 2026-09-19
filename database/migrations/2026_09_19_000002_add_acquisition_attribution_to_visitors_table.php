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
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('acquisition_source', 100)->nullable()->after('is_bot');
            $table->string('acquisition_medium', 100)->nullable()->after('acquisition_source');
            $table->string('acquisition_campaign', 100)->nullable()->after('acquisition_medium');
            $table->string('acquisition_content', 100)->nullable()->after('acquisition_campaign');
            $table->string('acquisition_term', 100)->nullable()->after('acquisition_content');
            $table->dateTime('acquisition_touch_at')->nullable()->after('acquisition_term');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('visitors') &&
            Schema::hasColumn('visitors', 'acquisition_source') &&
            DB::table('visitors')->whereNotNull('acquisition_source')->exists()
        ) {
            throw new RuntimeException('Cannot rollback migration 2026_09_19_000002: visitors table contains records with populated acquisition attribution. Reversing this migration would destroy first-touch attribution.');
        }

        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn([
                'acquisition_source',
                'acquisition_medium',
                'acquisition_campaign',
                'acquisition_content',
                'acquisition_term',
                'acquisition_touch_at',
            ]);
        });
    }
};
