<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentSessionContext;
use App\Domains\Students\Services\StudentTwoFactorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstimatedActivityGate
{
    /** The ordinary database is deliberately outside the only supported synthetic target. */
    public function isolated(): bool
    {
        $db = DB::connection();
        if (! app()->environment('testing', 'activity-acceptance') || $db->getDriverName() !== 'mysql'
            || $db->getDatabaseName() !== 'bolt_landing_test'
            || ! in_array($db->getConfig('host'), ['127.0.0.1', 'localhost'], true)
            || (string) $db->getConfig('port') !== '3307'
            || str_replace('\\', '/', storage_path()) === str_replace('\\', '/', base_path('storage'))) {
            return false;
        }
        $actual = $db->selectOne('SELECT DATABASE() AS name, @@port AS port');

        return $actual->name === 'bolt_landing_test' && (string) $actual->port === '3307';
    }

    public function runtimeHash(): string
    {
        $db = DB::connection();

        return EstimatedActivityDefinition::fingerprint(json_encode([app()->environment(), str_replace('\\', '/', base_path()), str_replace('\\', '/', storage_path()), $db->getDriverName(), $db->getConfig('host'), (string) $db->getConfig('port'), $db->getDatabaseName()], JSON_THROW_ON_ERROR));
    }

    public function principalFingerprint(Student|Administrator $principal): string
    {
        return $principal instanceof Student
            ? app(StudentTwoFactorService::class)->fingerprint($principal)
            : app(AdministratorTwoFactorService::class)->fingerprint($principal);
    }

    /** @return array{principal: Student|Administrator, run: object, grant: object, binding: string, domain: string}|null */
    public function allowed(Request $request, string $domain, bool $lock = false): ?array
    {
        if (config('estimated_activity.mode') !== 'synthetic' || ! in_array($domain, ['student', 'admin'], true)
            || ! $request->hasSession() || ! $this->isolated()
            || $request->session()->has('lms_preview') || $request->attributes->get('previewStudent')
            || $request->routeIs('*.preview*', 'admin.students.preview-portal')) {
            return null;
        }
        $path = storage_path('estimated-activity-runtime.json');
        if (! is_file($path)) {
            return null;
        }
        $seal = json_decode(file_get_contents($path), true);
        if (! is_array($seal) || ! isset($seal['run_id'], $seal['runtime_hash'], $seal['signature'])
            || ! hash_equals($this->runtimeHash(), $seal['runtime_hash'])
            || ! hash_equals(EstimatedActivityDefinition::fingerprint($seal['run_id'].'|'.$seal['runtime_hash']), $seal['signature'])
            || config('estimated_activity.run_id') !== $seal['run_id']) {
            return null;
        }
        if ($domain === 'student') {
            $principal = app(StudentSessionContext::class)->current($request);
            if (! $principal) {
                return null;
            }
            if ($lock) {
                Student::query()->whereKey($principal->id)->lockForUpdate()->firstOrFail();
                $principal = app(StudentSessionContext::class)->current($request);
                if (! $principal) {
                    return null;
                }
            }
        } else {
            $id = $request->user('web')?->id;
            $principal = Administrator::whereKey($id)->where('role', 'super_admin')->whereNull('suspended_at')
                ->when($lock, fn ($query) => $query->lockForUpdate())->first();
            if (! $principal || ! hash_equals($this->principalFingerprint($principal), (string) $request->session()->get('admin.session_fingerprint'))
                || ($principal->requiresTwoFactor() && ! hash_equals($this->principalFingerprint($principal), (string) $request->session()->get('admin.two_factor_verified')))
                || app(TutorWorkWorkspace::class)->find($lock)?->transition_state !== 'ready') {
                return null;
            }
        }
        $now = CarbonImmutable::now('UTC');
        $run = DB::table('estimated_activity_runs')->where('id', $seal['run_id'])->where('runtime_hash', $seal['runtime_hash'])
            ->where('enabled', true)->where('expires_at', '>', EstimatedActivityDefinition::stamp($now))
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if (! $run) {
            return null;
        }
        $grant = DB::table('estimated_activity_grants')->where('run_id', $run->id)->where('principal_type', $domain)->where('principal_id', $principal->id)
            ->where('principal_fingerprint', $this->principalFingerprint($principal))->whereNull('revoked_at')
            ->where('expires_at', '>', EstimatedActivityDefinition::stamp($now))->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if (! $grant) {
            return null;
        }

        return ['principal' => $principal, 'run' => $run, 'grant' => $grant, 'domain' => $domain,
            'binding' => EstimatedActivityDefinition::fingerprint($request->session()->getId().'|'.$this->principalFingerprint($principal).'|'.($domain === 'student' ? $request->session()->get('student_auth_expires_at') : 'staff'))];
    }

    /** @return array<string,mixed>|null */
    public function bootstrap(Request $request, string $kind, int $record, string $root): ?array
    {
        try {
            $domain = str_starts_with($kind, 'student_') ? 'student' : 'admin';
            $access = $this->allowed($request, $domain);
            $registry = app(EstimatedActivityRegistry::class);
            if (! $access || ! isset($registry::CONTEXTS[$kind]) || ! $request->routeIs(...$registry::CONTEXTS[$kind]['routes'])) {
                return null;
            }
            $registry->validate($access['principal'], $kind, $record);

            return ['kind' => $kind, 'record' => $record, 'root' => $root, 'definition' => EstimatedActivityDefinition::VERSION,
                'anchor' => route($domain.'.estimated-activity.anchor', $domain === 'student' ? ['lang' => app()->getLocale()] : []),
                'checkpoint' => route($domain.'.estimated-activity.checkpoint', $domain === 'student' ? ['lang' => app()->getLocale()] : []),
                'grace' => $domain === 'student' ? EstimatedActivityDefinition::STUDENT_GRACE_MS : EstimatedActivityDefinition::ADMIN_GRACE_MS,
                'window' => EstimatedActivityDefinition::WINDOW_MS, 'gap' => EstimatedActivityDefinition::MAX_GAP_MS];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $data @return list<array<string,mixed>> */
    public function surface(Request $request, string $view, array $data): array
    {
        if (config('estimated_activity.mode') !== 'synthetic') {
            return [];
        }
        $candidates = [];
        if ($view === 'student.learning.lesson') {
            foreach ($data['blocks'] as $block) {
                if (in_array($block['kind'], ['rich_text', 'image'], true)) {
                    $candidates[] = ['student_lesson', (int) $block['id'], 'activity-lesson-text-'.$block['id']];
                }
            }
            $candidates[] = ['student_note_draft', $data['lesson']->id, 'activity-note-new'];
            foreach ($data['notes'] as $note) {
                $candidates[] = ['student_note', $note->id, 'activity-note-'.$note->id];
            }
        } elseif ($view === 'student.learning.assessment') {
            $candidates[] = ['student_assessment', $data['block']->id, 'activity-assessment'];
        } elseif ($view === 'student.teaching') {
            foreach ($data['homework'] as $homework) {
                foreach (['study', 'response'] as $region) {
                    $candidates[] = ['student_homework', $homework->id, 'activity-homework-'.$region.'-'.$homework->id];
                }
            }
        } else {
            $candidates = match ($view) {
                'admin.lms.courses.create' => [['admin_course_create', 0, 'activity-course-create']],
                'admin.lms.courses.edit' => [['admin_course', $data['course']->id, 'activity-course-editor']],
                'admin.lms.reviews.index' => [['admin_lms_review', $data['course']->id, 'activity-review-content']],
                'admin.bookings.lesson-workspace' => [['admin_lesson_materials', $data['booking']->id, 'activity-lesson-materials']],
                'admin.feedback.show' => [['admin_feedback', $data['conversation']->id, 'activity-feedback']],
                'admin.bookings.show' => [['admin_booking', $data['booking']->id, 'activity-booking-schedule']],
                'admin.bookings.create' => [['admin_booking_create', 0, 'activity-booking-create-schedule']],
                'admin.students.teaching' => [
                    ['admin_teaching_homework', $data['student']->id, 'teaching-homework'],
                    ['admin_teaching_materials', $data['student']->id, 'teaching-resource'],
                    ['admin_teaching_plan', $data['student']->id, 'teaching-plan'],
                    ['admin_teaching_practice', $data['student']->id, 'teaching-error'],
                ],
                default => [],
            };
        }
        $result = [];
        foreach ($candidates as [$kind, $record, $root]) {
            if ($bootstrap = $this->bootstrap($request, $kind, $record, $root)) {
                $result[] = $bootstrap;
            }
        }

        return $result;
    }
}
