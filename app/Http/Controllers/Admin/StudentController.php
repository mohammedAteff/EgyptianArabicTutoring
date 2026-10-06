<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Services\StaffSavedViewService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentEmail;
use App\Domains\Students\Models\StudentOperationalAlert;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPackagePresentation;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\Students\Services\StudentRecordsQuery;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $students = $records->query($filters)->paginate(25)->withQueryString();

        return view('admin.students.index', ['students' => $students, 'search' => $filters['q'] ?? '', 'filters' => $filters, 'savedViews' => $views->forSection($request->user('web'), 'students'), 'totalStudents' => Student::tutoringRoster()->count()]);
    }

    public function export(Request $request, StudentRecordsQuery $records, ExportService $exports, TimezoneService $timezones): StreamedResponse|BinaryFileResponse
    {
        $filters = $records->filters($request);
        $rows = function () use ($records, $filters, $timezones): \Generator {
            foreach ($records->query($filters)->lazy(100) as $student) {
                $remaining = $student->packages->map(fn ($package): string => $package->package_name.' #'.$package->id.' · '.app(EntitlementService::class)->balanceText($package))->implode('; ');
                yield [$student->name, $student->email ?? '', $student->phone ?? '', $student->suspended_at ? 'Suspended' : $student->identity_status, $student->preferred_timezone ?? '', (int) $student->bookings_count, $remaining, $student->packages->pluck('package_name')->implode('; '), $student->packages->map(fn ($package) => $package->package_name.': '.($package->expiration_date?->toDateString() ?? 'No expiry'))->implode('; '), $student->created_at->setTimezone($timezones->getBusinessTimezone())->format('Y-m-d H:i'), $timezones->getBusinessTimezone()];
            }
        };

        return $exports->export('student_records', ['Student', 'Email', 'Phone', 'Status', 'Timezone', 'Sessions', 'Remaining Entitlements', 'Packages', 'Effective Expiry', 'Joined', 'Business Timezone'], $rows(), $filters['format'] ?? 'csv', 'Student Records');
    }

    public function show(Request $request, int $student, StudentPackagePresentation $presentation, TimezoneService $timezones): View
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
            ->get(['id', 'student_id', 'session_type_id', 'contact_id', 'start_at_utc', 'end_at_utc', 'business_timezone', 'customer_timezone', 'status', 'admin_reconfirmation_needed']);
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

        return view('admin.students.show', [
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
