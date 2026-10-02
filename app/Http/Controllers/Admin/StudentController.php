<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentEmail;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class StudentController extends Controller
{
    public function index(Request $request, StudentIdentityService $identity): View
    {
        $search = trim($request->string('q')->toString());
        $searchTerms = [];
        if ($search !== '') {
            $normalizedName = $identity->normalizeName($search);
            $normalizedEmail = $identity->normalizeEmail($search);
            $normalizedPhone = null;

            if (str_starts_with($search, '+')) {
                try {
                    $normalizedPhone = $identity->normalizePhone($search);
                } catch (InvalidArgumentException) {
                    $normalizedPhone = null;
                }
            }

            foreach ([
                'name_normalized' => $normalizedName,
                'email_normalized' => $normalizedEmail,
                'phone_normalized' => $normalizedPhone,
            ] as $column => $term) {
                if ($term !== null && $term !== '') {
                    $searchTerms[$column] = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                }
            }
        }

        $students = Student::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'phone', 'preferred_timezone', 'created_at', 'suspended_at', 'preferred_meeting_provider_id'])
            ->when($searchTerms !== [], function (Builder $query) use ($searchTerms): void {
                $query->where(function (Builder $query) use ($searchTerms): void {
                    foreach ($searchTerms as $index => $like) {
                        $column = $index;
                        if ($index === array_key_first($searchTerms)) {
                            $query->where($column, 'like', $like);

                            continue;
                        }

                        $query->orWhere($column, 'like', $like);
                    }
                });
            })
            ->orderBy('name_normalized')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.students.index', compact('students', 'search'));
    }

    public function show(Request $request, int $student, StudentLedgerService $ledger, TimezoneService $timezones): View
    {
        $isAssistant = $request->user('web')?->role === 'assistant';
        $columns = ['id', 'first_name', 'last_name', 'email', 'phone', 'preferred_timezone', 'created_at', 'suspended_at', 'preferred_meeting_provider_id'];
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

        $financialPackages = collect();
        if (! $isAssistant) {
            $packages = StudentPackage::query()->where('student_id', $studentRecord->id)->orderByDesc('created_at')->orderByDesc('id')->get();
            $financialPackages = $packages->map(function (StudentPackage $package) use ($ledger): array {
                return [
                    'package' => $package,
                    'summary' => $ledger->summary($package),
                    'payments' => PaymentRecord::query()->where('student_package_id', $package->id)->orderByDesc('paid_at')->get(),
                    'refunds' => PaymentRefund::query()->where('student_package_id', $package->id)->orderByDesc('refunded_at')->get(),
                    'validityHistory' => AuditLog::query()->where('entity_type', StudentPackage::class)->where('entity_id', $package->id)->where('action', 'package_validity_extended')->orderByDesc('id')->get(),
                    'entries' => SessionLedgerEntry::query()->where('student_package_id', $package->id)->orderByDesc('id')->get(),
                ];
            });
        }

        return view('admin.students.show', [
            'student' => $studentRecord,
            'bookings' => $bookings,
            'formSubmissions' => $formSubmissions,
            'financialPackages' => $financialPackages,
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
