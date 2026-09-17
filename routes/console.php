<?php

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\CMS\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// 1. Scheduler Heartbeat: executes every minute to prove host cron is active
Schedule::call(function () {
    Setting::set('last_scheduler_run_at', now('UTC')->toIso8601String(), 'system');
})->everyMinute()->name('scheduler-heartbeat');

// 2. Booking Holds Cleanup: purges expired and released reservation holds
Schedule::command('booking:cleanup-holds')
    ->everyFiveMinutes()
    ->onSuccess(function () {
        Setting::set('last_holds_cleanup_at', now('UTC')->toIso8601String(), 'system');
    })
    ->onFailure(function () {
        Setting::set('last_holds_cleanup_status', 'failed', 'system');
        try {
            app(AdminNotificationService::class)->notifySystemWarning(
                'Hold Cleanup Failed',
                'Scheduled task [booking:cleanup-holds] failed.'
            );
        } catch (Throwable) {
        }
    });

// 3. Analytics Aggregation: computes daily traffic and conversion rollups
Schedule::command('analytics:aggregate-daily --prune')
    ->dailyAt('00:05')
    ->onSuccess(function () {
        Setting::set('last_analytics_aggregation_at', now('UTC')->toIso8601String(), 'system');
    })
    ->onFailure(function () {
        Setting::set('last_analytics_aggregation_status', 'failed', 'system');
        try {
            app(AdminNotificationService::class)->notifySystemWarning(
                'Analytics Rollup Failed',
                'Scheduled task [analytics:aggregate-daily --prune] failed.'
            );
        } catch (Throwable) {
        }
    });

// 4. Daily Database & Storage Backup: point-in-time snapshot with integrity manifest
Schedule::command('backup:run --clean')
    ->dailyAt('02:00')
    ->onFailure(function () {
        try {
            app(AdminNotificationService::class)->notifyBackupFailed('Daily scheduled backup execution failed.');
        } catch (Throwable) {
        }
    });

// 5. Session and Auth Cleanup: purges expired database sessions and password reset tokens
Schedule::call(function () {
    Artisan::call('auth:clear-resets');

    if (config('session.driver') === 'database') {
        $lifetime = (int) config('session.lifetime', 120);
        DB::table(config('session.table', 'sessions'))
            ->where('last_activity', '<', now()->subMinutes($lifetime)->getTimestamp())
            ->delete();
    }
})->dailyAt('03:00')->name('session-cleanup');
