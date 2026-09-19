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
        // Preflight: identify and reconcile duplicate groups resulting from NULL dimensions
        $duplicates = DB::table('daily_metrics')
            ->select(
                'metric_date',
                'metric_name',
                DB::raw("COALESCE(dimension_key, '') as norm_key"),
                DB::raw("COALESCE(dimension_value, '') as norm_val"),
                DB::raw('COUNT(*) as duplicate_count'),
                DB::raw('SUM(count) as aggregate_value')
            )
            ->groupBy('metric_date', 'metric_name', DB::raw("COALESCE(dimension_key, '')"), DB::raw("COALESCE(dimension_value, '')"))
            ->having('duplicate_count', '>', 1)
            ->get();

        // Preflight: identify and reconcile duplicate groups inside an atomic MySQL transaction
        DB::transaction(function () use ($duplicates) {
            foreach ($duplicates as $group) {
                $matchingRows = DB::table('daily_metrics')
                    ->where('metric_date', $group->metric_date)
                    ->where('metric_name', $group->metric_name)
                    ->where(function ($q) use ($group) {
                        if ($group->norm_key === '') {
                            $q->whereNull('dimension_key')->orWhere('dimension_key', '');
                        } else {
                            $q->where('dimension_key', $group->norm_key);
                        }
                    })
                    ->where(function ($q) use ($group) {
                        if ($group->norm_val === '') {
                            $q->whereNull('dimension_value')->orWhere('dimension_value', '');
                        } else {
                            $q->where('dimension_value', $group->norm_val);
                        }
                    })
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($matchingRows->count() > 1) {
                    $primaryRow = $matchingRows->first();
                    $otherIds = $matchingRows->slice(1)->pluck('id')->all();

                    // Delete colliding rows FIRST before updating primary row to avoid MySQL 1062 duplicate key collision
                    DB::table('daily_metrics')->whereIn('id', $otherIds)->delete();

                    // Keep primary row with aggregated value and normalized empty string dimensions
                    DB::table('daily_metrics')
                        ->where('id', $primaryRow->id)
                        ->update([
                            'count' => (int) $group->aggregate_value,
                            'dimension_key' => $group->norm_key,
                            'dimension_value' => $group->norm_val,
                        ]);
                }
            }

            DB::table('daily_metrics')->whereNull('dimension_key')->update(['dimension_key' => '']);
            DB::table('daily_metrics')->whereNull('dimension_value')->update(['dimension_value' => '']);
        });

        Schema::table('daily_metrics', function (Blueprint $table) {
            $table->string('dimension_key', 64)->default('')->nullable(false)->change();
            $table->string('dimension_value', 128)->default('')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_metrics', function (Blueprint $table) {
            $table->string('dimension_key', 64)->nullable()->default(null)->change();
            $table->string('dimension_value', 128)->nullable()->default(null)->change();
        });
    }
};
