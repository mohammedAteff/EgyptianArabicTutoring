<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\StaffTask;
use App\Domains\Administration\Services\StaffSavedViewService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Lms\Services\LmsTutoringReadModel;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentEmail;
use App\Domains\Students\Models\StudentOperationalAlert;
use App\Domains\Students\Models\StudentPortalLogin;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentCompletenessService;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Students\Services\StudentLifetimeSummary;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPackagePresentation;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\Students\Services\StudentRecordsQuery;
use App\Domains\Students\Services\TeachingRecordService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentController extends Controller
{
    public function index(Request $request, StudentRecordsQuery $records, StaffSavedViewService $views): View
    {
        $filters = $records->filters($request);
        $students = $records->query($filters, $request->user('web')->id)->paginate(25)->withQueryString();
        $request->session()->put('staff_student_list_query', $filters + ['page' => $students->currentPage()]);
        $pinnedStudentIds = $request->user('web')->pinnedStudents()->pluck('student_id')->all();

        return view('admin.students.index', [
            'students' => $students,
            'search' => $filters['q'] ?? '',
            'filters' => $filters,
            'savedViews' => $views->forSection($request->user('web'), 'students'),
            'totalStudents' => Student::tutoringRoster()->count(),
            'pinnedStudentIds' => $pinnedStudentIds,
            'bulkResources' => $request->user('web')->isAdmin() ? \App\Domains\Resources\Models\Resource::query()->published()->orderBy('title')->get(['id', 'title']) : collect(),
        ]);
    }

    public function export(Request $request, StudentRecordsQuery $records, ExportService $exports, TimezoneService $timezones): StreamedResponse|BinaryFileResponse
    {
        $filters = $records->filters($request);
        $selectedIds = $request->input('student_ids') ?: $request->input('ids');
        $query = $records->query($filters, $request->user('web')->id);
        if (! empty($selectedIds) && is_array($selectedIds)) {
            $query->whereIn('students.id', $selectedIds);
        }
        $rows = function () use ($query, $timezones): \Generator {
            foreach ($query->lazy(100) as $student) {
                $remaining = $student->packages->map(fn ($package): string => $package->package_name.' #'.$package->id.' · '.app(EntitlementService::class)->balanceText($package))->implode('; ');
                yield [$student->name, $student->email ?? '', $student->phone ?? '', $student->suspended_at ? 'Suspended' : $student->identity_status, $student->preferred_timezone ?? '', (int) $student->bookings_count, $remaining, $student->packages->pluck('package_name')->implode('; '), $student->packages->map(fn ($package) => $package->package_name.': '.($package->expiration_date?->toDateString() ?? 'No expiry'))->implode('; '), $student->created_at->setTimezone($timezones->getBusinessTimezone())->format('Y-m-d H:i'), $timezones->getBusinessTimezone()];
            }
        };

        return $exports->export('student_records', ['Student', 'Email', 'Phone', 'Status', 'Timezone', 'Sessions', 'Remaining Entitlements', 'Packages', 'Effective Expiry', 'Joined', 'Business Timezone'], $rows(), $filters['format'] ?? 'csv', 'Student Records');
    }

    public function bulkTask(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
        ]);

        $assigneeId = $request->user('web')->id;

        foreach ($data['student_ids'] as $studentId) {
            $task = StaffTask::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'student_id' => $studentId,
                'assignee_id' => $assigneeId,
                'created_by' => $assigneeId,
                'due_date' => $data['due_date'] ?? null,
                'priority' => $data['priority'],
                'status' => 'open',
            ]);

            $audit->log('staff_task_created', StaffTask::class, $task->id, null, [
                'bulk' => true,
                'student_id' => $studentId,
                'assignee_id' => $assigneeId,
            ], $assigneeId);
        }

        return back()->with('success', count($data['student_ids']).' tasks created.');
    }

    public function bulkTeaching(Request $request, TeachingRecordService $teaching): RedirectResponse
    {
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1', 'max:100'], 'student_ids.*' => ['required', 'integer', 'distinct'], 'action' => ['required', 'in:resource,tag'],
            'resource_id' => ['nullable', 'required_if:action,resource', 'integer'], 'label' => ['nullable', 'required_if:action,tag', 'string', 'max:40'], 'student_visible' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $data, $teaching): void {
            $students = Student::query()->whereIn('id', $data['student_ids'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($students->count() === count($data['student_ids']), 404);
            foreach ($students as $student) {
                Gate::authorize('manageTeaching', $student);
                if ($data['action'] === 'resource') {
                    $existing = ResourceAssignment::query()->where('student_id', $student->id)->where('resource_id', $data['resource_id'])->whereNull('booking_id')->first();
                    $payload = ['resource_id' => $data['resource_id'], 'student_visible' => $data['student_visible'], 'record_id' => $existing?->id];
                } else {
                    $payload = ['label' => $data['label'], 'student_visible' => $data['student_visible']];
                }
                $teaching->save($student, $data['action'], $payload, $request->user('web')->id);
            }
        }, 3);

        return back()->with('success', 'Selected students updated.');
    }

    public function show(Request $request, int $student, StudentPackagePresentation $presentation, TimezoneService $timezones, LmsTutoringReadModel $learning): View
    {
        $isAssistant = $request->user('web')?->role === 'assistant';
        $columns = ['id', 'first_name', 'last_name', 'email', 'phone', 'preferred_timezone', 'created_at', 'suspended_at', 'preferred_meeting_provider_id', 'operational_status'];
        if (! $isAssistant) {
            array_push($columns, 'name_normalized', 'email_normalized', 'phone_normalized', 'date_of_birth', 'identity_status', 'possible_duplicate_of_student_id', 'internal_notes');
        }
        $studentRecord = Student::query()->select($columns)->findOrFail($student);
        $bookings = Booking::query()
            ->where('student_id', $studentRecord->id)
            ->with('sessionType:id,title,duration_minutes')
            ->orderByDesc('start_at_utc')
            ->get(['id', 'student_id', 'session_type_id', 'contact_id', 'start_at_utc', 'end_at_utc', 'business_timezone', 'customer_timezone', 'status', 'admin_reconfirmation_needed', ...($isAssistant ? [] : ['meeting_provider_snapshot', 'meeting_url_snapshot'])]);
        $formSubmissions = FormSubmission::query()
            ->where('student_id', $studentRecord->id)
            ->with([
                'version.form:id,title,slug',
                'answers' => fn ($query) => $query->select(['id', 'form_submission_id', 'form_question_id', 'value_text'])
                    ->when($isAssistant, fn ($answers) => $answers->whereHas('question', fn ($questions) => $questions->where('assistant_visible', true))),
                'answers.question:id,label,assistant_visible',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get(['id', 'form_version_id', 'student_id', 'status', 'submitted_at', 'submission_revision']);
        $financial = ['packages' => collect(), 'selected' => null, 'history' => collect()];
        $billingTab = 'overview';
        $historyType = 'all';
        if (! $isAssistant) {
            $filters = $request->validate([
                'package_id' => ['nullable', 'integer', 'min:1'],
                'billing_tab' => ['nullable', Rule::in(['overview', 'payments', 'credits', 'expiration', 'history'])],
                'history_type' => ['nullable', Rule::in(['all', 'payments', 'refunds', 'credits', 'expiry'])],
            ]);
            $billingTab = $filters['billing_tab'] ?? 'overview';
            $historyType = $filters['history_type'] ?? 'all';
            $financial = $presentation->forStudent($studentRecord->id, isset($filters['package_id']) ? (int) $filters['package_id'] : null, $historyType);
        }

        $lifetimeSummary = $isAssistant ? null : app(StudentLifetimeSummary::class)
            ->forStudent($studentRecord, $bookings, $financial['packages']);
        $verifiedSecondaryEmails = $studentRecord->verifiedEmails()->orderByDesc('verified_at')->get();
        $guardians = $studentRecord->guardians()->orderByDesc('is_primary')->orderBy('name')->get();
        $isPinned = $studentRecord->pinnedByStaff()->where('administrator_id', $request->user('web')->id)->exists();
        $completeness = $isAssistant ? null : app(StudentCompletenessService::class)->assess($studentRecord);

        return view('admin.students.show', [
            'learningProfile' => ! $isAssistant && Gate::forUser($request->user('web'))->allows('manageTeaching', $studentRecord->fresh())
                ? $learning->profile($request->user('web'), $studentRecord) : null,
            'operationalAlerts' => StudentOperationalAlert::query()->where('student_id', $studentRecord->id)->with('author')->orderByRaw("status = 'active' desc")->orderByDesc('updated_at')->get(),
            'student' => $studentRecord,
            'bookings' => $bookings,
            'formSubmissions' => $formSubmissions,
            'financialPackages' => $financial['packages'],
            'selectedFinancial' => $financial['selected'],
            'packageHistory' => $financial['history'],
            'billingTab' => $billingTab,
            'historyType' => $historyType,
            'meetingProviders' => $isAssistant ? collect() : MeetingProvider::query()->where('active', true)->orderBy('sort_order')->get(),
            'entitlementTypes' => EntitlementType::query()->where('active', true)->get(),
            'paymentMethods' => $isAssistant ? collect() : PaymentMethod::available()->get(),
            'isAssistant' => $isAssistant,
            'businessTz' => $timezones->getBusinessTimezone(),
            'lifetimeSummary' => $lifetimeSummary,
            'studentListUrl' => route('admin.students.index', $request->session()->get('staff_student_list_query', [])),
            'copyBlocks' => $isAssistant ? [] : app(StudentLifetimeSummary::class)->copyBlocks($studentRecord, $bookings, $financial['packages'], $timezones->getBusinessTimezone()),
            'portalLogins' => $isAssistant ? collect() : StudentPortalLogin::query()->where('student_id', $studentRecord->id)->orderByDesc('authenticated_at')->limit(5)->get(),
            'verifiedSecondaryEmails' => $verifiedSecondaryEmails,
            'guardians' => $guardians,
            'isPinned' => $isPinned,
            'completeness' => $completeness,
        ]);
    }

    public function update(
        Request $request,
        int $student,
        StudentIdentityService $identity,
        TimezoneService $timezones,
        AuditLogService $auditLogs,
        DatabaseCapability $database
    ): RedirectResponse {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'preferred_timezone' => ['nullable', 'string', 'max:64'],
            'identity_status' => ['required', Rule::in(['verified', 'legacy_unverified'])],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $email = $identity->normalizeEmail($validated['email'] ?? null);
        try {
            $phone = $identity->normalizePhone($validated['phone'] ?? null, $validated['phone_country'] ?? null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }
        if ($validated['identity_status'] === 'verified' && (empty($validated['date_of_birth']) || ($email === null && $phone === null))) {
            throw ValidationException::withMessages(['identity_status' => 'A verified student requires a date of birth and at least one email address or phone number.']);
        }
        $timezone = null;
        if (! empty($validated['preferred_timezone'])) {
            try {
                $timezone = $timezones->validate($validated['preferred_timezone']);
            } catch (Throwable) {
                throw ValidationException::withMessages(['preferred_timezone' => 'Choose a valid IANA timezone.']);
            }
        }
        $database->transaction(function () use ($student, $validated, $identity, $email, $phone, $timezone, $auditLogs, $request): void {
            $record = Student::query()->whereKey($student)->lockForUpdate()->firstOrFail();
            if ($email && StudentEmail::query()->where('email_normalized', $email)->where('student_id', '!=', $record->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['email' => 'This email belongs to another verified student profile.']);
            }
            $previous = [
                'first_name' => $record->first_name,
                'last_name' => $record->last_name,
                'email' => $record->email,
                'phone' => $record->phone,
                'date_of_birth' => $record->date_of_birth?->toDateString(),
                'identity_status' => $record->identity_status,
            ];
            $record->forceFill([
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'name_normalized' => $identity->normalizedFullName($validated['first_name'], $validated['last_name']),
                'email' => $email,
                'email_normalized' => $email,
                'phone' => $phone,
                'phone_normalized' => $phone,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'preferred_timezone' => $timezone,
                'identity_status' => $validated['identity_status'],
                'internal_notes' => $validated['internal_notes'] ?? null,
            ])->save();
            Cache::forget('student_auth_check_'.$record->id);
            $auditLogs->log(
                action: 'student_identity_updated',
                entityType: Student::class,
                entityId: $record->id,
                previousData: $previous,
                newData: [
                    'first_name' => $record->first_name,
                    'last_name' => $record->last_name,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date_of_birth' => $record->date_of_birth?->toDateString(),
                    'identity_status' => $record->identity_status,
                ],
                adminId: $request->user('web')?->id,
            );
        }, 3);

        return redirect()->route('admin.students.show', $student)->with('success', 'Student identity details updated.');
    }

    public function merge(Request $request, int $student, StudentMergeService $merges): RedirectResponse
    {
        $validated = $request->validate([
            'secondary_student_id' => ['required', 'integer', Rule::notIn([$student])],
        ]);
        $merges->merge((int) $student, (int) $validated['secondary_student_id'], $request->user('web')?->id);

        return redirect()->route('admin.students.show', $student)->with('success', 'Student records and their histories were merged.');
    }

    public function anonymize(Request $request, int $student, StudentPrivacyService $privacy): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'in:ANONYMIZE']]);
        $privacy->anonymize($student, $request->user('web')?->id);

        return redirect()->route('admin.students.index')->with('success', 'Student personal data was anonymized; historical records were retained.');
    }
}
