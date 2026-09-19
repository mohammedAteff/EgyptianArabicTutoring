<?php

namespace App\Providers;

use App\Domains\Administration\Services\AdminNotificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once __DIR__.'/../helpers.php';
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('booking-reschedule', function (Request $request) {
            $token = (string) $request->route('token');

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by('reschedule:token:'.$token),
            ];
        });

        RateLimiter::for('booking-cancel', function (Request $request) {
            $token = (string) $request->route('token');

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by('cancel:token:'.$token),
            ];
        });

        RateLimiter::for('resource-request', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by('resource:email:'.$email),
            ];
        });

        RateLimiter::for('game-track', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('password-reset-request', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes(15, 5)->by($request->ip()),
                Limit::perMinutes(15, 5)->by('pwd-reset:email:'.$email),
            ];
        });

        RateLimiter::for('password-reset-attempt', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes(15, 5)->by($request->ip()),
                Limit::perMinutes(15, 5)->by('pwd-attempt:email:'.$email),
            ];
        });

        Queue::looping(function () {
            Cache::put('queue_worker_heartbeat_at', now('UTC')->toIso8601String(), 300);
        });

        Queue::before(function () {
            Cache::put('queue_worker_heartbeat_at', now('UTC')->toIso8601String(), 300);
        });

        Queue::failing(function (JobFailed $event) {
            try {
                app(AdminNotificationService::class)->notifyJobFailed(
                    $event->connectionName,
                    $event->job->getQueue(),
                    $event->job->resolveName(),
                    $event->exception->getMessage()
                );
            } catch (\Throwable) {
                // Ignore failure in dispatching internal notification
            }
        });

        // Ensure JSON static strings missing in non-English locales fall back to English
        $this->app->make('translator')->handleMissingKeysUsing(function (string $key, array $replace, ?string $locale, bool $fallback) {
            $fallbackLocale = config('app.fallback_locale', 'en');
            if ($locale !== $fallbackLocale) {
                return $this->app->make('translator')->get($key, $replace, $fallbackLocale, false);
            }

            return $key;
        });
    }
}
