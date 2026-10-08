<?php

namespace App\Providers;

use App\Domains\Administration\Models\StaffBin;
use App\Domains\Administration\Models\StaffTask;
use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Analytics\Services\AnalyticsLifecycle;
use App\Domains\Audit\Services\PrivacyDatabaseSessionHandler;
use App\Domains\Audit\Services\TransientRateLimitKey;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\CMS\Services\AnnouncementService;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\BunnyStreamProvider;
use App\Domains\Lms\Services\LmsSettings;
use App\Domains\Lms\Services\VideoDeliveryProvider;
use App\Domains\Notifications\Services\TelegramBusinessEvents;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentOperationalAlert;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\System\Services\DevelopmentDataSchema;
use App\Policies\LessonMaterialPolicy;
use App\Policies\LessonWorkspacePolicy;
use App\Policies\LmsPolicy;
use App\Policies\StaffBinPolicy;
use App\Policies\StaffTaskPolicy;
use App\Policies\StudentBinPolicy;
use App\Policies\StudentOperationalAlertPolicy;
use App\Policies\StudentTeachingPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VideoDeliveryProvider::class, BunnyStreamProvider::class);
        $this->app->scoped(DevelopmentDataSchema::class);
        $this->app->scoped(AnalyticsLifecycle::class);
        $this->app->scoped(LmsSettings::class);
        ini_set('unserialize_callback_func', 'spl_autoload_call');
        $serializable = config('cache.serializable_classes');
        if (is_array($serializable)) {
            config(['cache.serializable_classes' => array_values(array_unique(array_merge($serializable, [
                Student::class,
            ])))]);
        } elseif ($serializable === false) {
            config(['cache.serializable_classes' => [
                Student::class,
            ]]);
        }
        require_once __DIR__.'/../helpers.php';
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.public', 'layouts.student'], function (\Illuminate\View\View $view): void {
            $view->with('siteAnnouncement', app(AnnouncementService::class)->visible($view->name() === 'layouts.student' ? 'student' : 'public'));
        });
        Session::extend('database', fn ($app) => new PrivacyDatabaseSessionHandler(
            DB::connection(config('session.connection')), config('session.table'), (int) config('session.lifetime'), $app,
        ));
        if ($this->app->environment('testing')) {
            @ini_set('memory_limit', '512M');
        }

        Gate::policy(StaffBin::class, StaffBinPolicy::class);
        Gate::policy(StaffTask::class, StaffTaskPolicy::class);
        Gate::policy(StudentOperationalAlert::class, StudentOperationalAlertPolicy::class);
        Gate::policy(StudentBin::class, StudentBinPolicy::class);
        Gate::policy(Booking::class, LessonWorkspacePolicy::class);
        Gate::policy(LessonMaterial::class, LessonMaterialPolicy::class);
        Gate::policy(Student::class, StudentTeachingPolicy::class);
        foreach ([Course::class, Section::class, Lesson::class, LessonBlock::class, AccessGrant::class, Enrollment::class, LearningAssignment::class] as $learningModel) {
            Gate::policy($learningModel, LmsPolicy::class);
        }
        class_exists(Student::class);
        app(TelegramBusinessEvents::class)->register();

        RateLimiter::for('student-verification', function (Request $request) {
            $email = $request->input('email');
            $phone = $request->input('phone');
            $dateOfBirth = $request->input('date_of_birth');
            $phoneCountry = $request->input('phone_country');
            $fingerprints = app(StudentIdentityService::class)->authFingerprints(
                is_string($email) ? $email : null,
                is_string($phone) ? $phone : null,
                is_string($dateOfBirth) ? $dateOfBirth : null,
                is_string($phoneCountry) ? $phoneCountry : null,
            );

            $limits = [
                Limit::perMinutes(10, 5)->by('student-auth:ip:10m:'.$request->ip()),
                Limit::perMinutes(1440, 50)->by('student-auth:ip:24h:'.$request->ip()),
            ];
            foreach ($fingerprints as $kind => $digest) {
                $limits[] = Limit::perMinutes(15, 5)->by('student-auth:'.$kind.':'.$digest);
            }

            return $limits;
        });

        RateLimiter::for('staff-two-factor-challenge', function (Request $request): array {
            return [
                Limit::perMinute(5)->by(TransientRateLimitKey::make('staff-2fa-session', $request->session()->getId())),
                Limit::perMinute(10)->by(TransientRateLimitKey::make('staff-2fa-account', (string) $request->session()->get('admin.two_factor_pending.id', 'none'))),
                Limit::perMinute(30)->by(TransientRateLimitKey::make('staff-2fa-connection', (string) $request->ip())),
            ];
        });
        RateLimiter::for('staff-two-factor-security', function (Request $request): array {
            return [
                Limit::perMinute(10)->by(TransientRateLimitKey::make('staff-2fa-settings', (string) $request->user('web')?->getAuthIdentifier())),
                Limit::perMinute(30)->by(TransientRateLimitKey::make('staff-2fa-settings-connection', (string) $request->ip())),
            ];
        });

        RateLimiter::for('student-form-save', function (Request $request) {
            $studentId = (string) $request->session()->get('student_id', 'anonymous');

            return [
                Limit::perMinute(30)->by('student-form:ip:'.$request->ip()),
                Limit::perMinute(20)->by('student-form:student:'.$studentId),
            ];
        });

        RateLimiter::for('student-booking-finalize', function (Request $request) {
            $studentId = (string) $request->session()->get('student_id', 'anonymous');

            return [
                Limit::perMinutes(5, 10)->by('student-booking:ip:'.$request->ip()),
                Limit::perMinutes(5, 5)->by('student-booking:student:'.$studentId),
            ];
        });

        RateLimiter::for('booking-reschedule', function (Request $request) {
            $token = (string) $request->route('token');
            $booking = (string) $request->route('booking');
            $studentId = (string) $request->session()->get('student_id', 'anonymous');
            $identityKey = $token !== ''
                ? 'reschedule:token:'.$token
                : 'reschedule:student:'.$studentId.':booking:'.$booking;

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by($identityKey),
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
            $challenge = (string) $request->input('challenge');

            $limits = [
                Limit::perMinute(10)->by($request->ip()),
            ];

            if ($email !== '') {
                $limits[] = Limit::perMinute(5)->by('resource:email:'.$email);
            } elseif ($challenge !== '') {
                $limits[] = Limit::perMinute(5)->by('resource:challenge:'.$challenge);
            }

            return $limits;
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
