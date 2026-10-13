<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingPolicyService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Services\FormCompletionReadModel;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DataQualityReadModel
{
    /** @return array<string, mixed> */
    public function overview(): array
    {
        $missing = Student::query()->where('identity_status', '!=', 'merged')->where(fn ($query) => $query->whereNull('email_normalized')->orWhereNull('phone_normalized')->orWhereNull('preferred_timezone')->orWhereNull('date_of_birth'));
        $packages = StudentPackage::query()->where(fn ($query) => $query->where('identity_state', 'legacy_unclassified')->orWhereDoesntHave('entitlements')->orWhereDoesntHave('ledgerEntries', fn ($ledger) => $ledger->where('entry_type', 'package_grant')));
        $configuration = [];
        $timezone = Setting::get('business_timezone', config('app.timezone'));
        if (! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $configuration[] = 'Business timezone requires review.';
        }
        $policy = Setting::get('booking_lifecycle_policy');
        if ($policy !== null && (! is_array($policy) || Validator::make($policy, app(BookingPolicyService::class)->rules())->fails())) {
            $configuration[] = 'Cancellation / no-show policy requires review. New bookings use the documented defaults until corrected.';
        }
        $invalidTypes = SessionType::query()->where('active', true)->where('funding_mode', 'package')
            ->where(fn ($query) => $query->whereNull('required_entitlement_type_id')->orWhere('required_entitlement_units', '<', 1)->orWhereDoesntHave('requiredEntitlementType', fn ($types) => $types->where('active', true)))->count();
        if ($invalidTypes > 0) {
            $configuration[] = $invalidTypes.' active package-funded session types require entitlement review.';
        }
        $orphans = [
            'Bookings without a student relationship' => DB::table('bookings')->whereNull('student_id')->count(),
            'Package allocation ownership mismatch' => DB::table('student_package_entitlements as e')->join('student_packages as p', 'p.id', '=', 'e.student_package_id')->whereColumn('e.student_id', '!=', 'p.student_id')->count(),
            'Payments with a package ownership mismatch' => DB::table('payment_records as m')->join('student_packages as p', 'p.id', '=', 'm.student_package_id')->whereColumn('m.student_id', '!=', 'p.student_id')->count(),
        ];

        return ['missingCount' => (clone $missing)->count(), 'missingStudents' => $missing->orderBy('id')->limit(30)->get(),
            'packageCount' => (clone $packages)->count(), 'packages' => $packages->with('student')->orderBy('id')->limit(30)->get(),
            'configuration' => $configuration, 'relationships' => $orphans,
            'businessIssues' => $this->businessIssues(), 'studentDuplicates' => $this->duplicates('student'), 'contactDuplicates' => $this->duplicates('contact')];
    }

    /** @return array<string, array{count: int, records: list<array{label: string, url: string}>}> */
    public function businessIssues(): array
    {
        $issues = [];
        $add = function (string $label, $query, callable $link) use (&$issues): void {
            $issues[$label] = ['count' => (clone $query)->count(), 'records' => $query->limit(30)->get()->map($link)->all()];
        };
        $future = Booking::query()->where('start_at_utc', '>', now('UTC'))->whereIn('status', ['confirmed', 'held', 'pending']);
        $bookingLink = fn ($booking): array => ['label' => 'Session #'.$booking->id, 'url' => route('admin.bookings.show', $booking->id)];
        $studentLink = fn ($student): array => ['label' => $student->name, 'url' => route('admin.students.show', $student->id)];
        $packageLink = fn ($package): array => ['label' => 'Purchase #'.$package->id.' · '.$package->package_name, 'url' => route('admin.packages.lifecycle', $package->id)];
        $add('Upcoming sessions missing a meeting room', (clone $future)->whereNull('meeting_room_id'), $bookingLink);
        $add('Suspended students with upcoming sessions', (clone $future)->whereHas('student', fn ($query) => $query->whereNotNull('suspended_at')), $bookingLink);
        $add('Students without a trusted email contact', Student::query()->where('identity_status', '!=', 'merged')->where(fn ($query) => $query->whereNull('email')->orWhere('email', ''))->whereDoesntHave('verifiedEmails'), $studentLink);
        $pending = Student::query()->whereIn('id', DB::table('student_email_verifications')->whereNull('consumed_at')->where('expires_at', '>', now('UTC'))->select('student_id'));
        $add('Email verification challenges awaiting completion', $pending, $studentLink);
        $invalidExpiry = StudentPackage::query()->where('status', 'active')->where(function ($query): void {
            $query->whereColumn('expires_at', '<=', 'validity_started_at')->orWhere(function ($query): void {
                $query->whereNotNull('validity_days')->whereNull('expires_at')->whereNull('expiration_date')
                    ->whereRaw('(select coalesce(sum(amount_paid),0) from payment_records where student_package_id = student_packages.id) - (select coalesce(sum(amount_refunded),0) from payment_refunds where student_package_id = student_packages.id) >= final_price');
            });
        })->whereHas('ledgerEntries', fn ($query) => $query->where('credit_change', '>', 0));
        $add('Settled purchases with invalid or missing validity', $invalidExpiry, $packageLink);
        $overRefund = PaymentRecord::query()->whereRaw('(select coalesce(sum(amount_refunded),0) from payment_refunds where payment_record_id = payment_records.id) > amount_paid');
        $add('Refund totals exceeding their original payment', $overRefund, fn ($payment): array => ['label' => 'Payment PAY-'.$payment->id, 'url' => route('admin.packages.lifecycle', $payment->student_package_id)]);
        $negative = StudentPackage::query()->whereExists(function ($query): void {
            $query->selectRaw('1')->from('session_ledger_entries')->whereColumn('student_package_id', 'student_packages.id')->groupBy('student_package_entitlement_id')->havingRaw('SUM(credit_change) < 0');
        });
        $add('Purchases with a negative entitlement balance', $negative, $packageLink);
        $overdue = StudentPackage::query()->whereHas('installments', fn ($query) => $query->where('status', 'expected')->where('due_date', '<', now(app(TimezoneService::class)->getBusinessTimezone())->toDateString()))
            ->whereRaw('final_price > (select coalesce(sum(amount_paid),0) from payment_records where student_package_id = student_packages.id) - (select coalesce(sum(amount_refunded),0) from payment_refunds where student_package_id = student_packages.id)');
        $add('Overdue expected installments', $overdue, $packageLink);
        $pool = (clone $future)->whereRaw('not exists (select 1 from meeting_rooms r join meeting_providers mp on mp.id = r.meeting_provider_id and mp.active = 1 where r.active = 1 and r.meeting_provider_id = coalesce((select preferred_meeting_provider_id from students where students.id = bookings.student_id), (select id from meeting_providers where is_default = 1 and active = 1 order by id limit 1)))');
        $add('Upcoming sessions without an available provider room pool', $pool, $bookingLink);
        $resources = ResourceAssignment::query()->where('student_visible', true)->whereDoesntHave('resource', fn ($query) => $query->published());
        $add('Assigned resource activity without a published resource', $resources, fn ($assignment): array => ['label' => 'Resource assignment #'.$assignment->id, 'url' => route('admin.students.teaching', $assignment->student_id)]);
        $missingForms = [];
        foreach (Student::query()->where('operational_status', 'active')->whereHas('bookings', fn ($query) => $query->where('start_at_utc', '>', now('UTC'))->whereIn('status', ['confirmed', 'held', 'pending']))->orderBy('id')->get() as $student) {
            if (app(FormCompletionReadModel::class)->forStudent($student)->contains(fn (array $form): bool => $form['mandatory'] && $form['status'] !== 'submitted')) {
                $missingForms[] = $studentLink($student);
            }
        }
        $issues['Upcoming students missing mandatory forms'] = ['count' => count($missingForms), 'records' => array_slice($missingForms, 0, 30)];
        $orphans = DB::table('payment_records')->whereNull('student_package_id')->count();
        $issues['Payments without a purchase association'] = ['count' => $orphans, 'records' => $orphans ? [['label' => 'Review payment associations', 'url' => route('admin.billing.reconcile')]] : []];

        return $issues;
    }

    /** @return array<int, array{first: int, second: int, signal: string}> */
    public function duplicates(string $kind, int $afterId = 0): array
    {
        $identity = app(StudentIdentityService::class);
        $records = $kind === 'student'
            ? Student::query()->where('identity_status', '!=', 'merged')->where('id', '>', $afterId)->orderBy('id')->limit(1000)->get(['id', 'first_name', 'last_name', 'email', 'phone'])
            : Contact::query()->where('id', '>', $afterId)->orderBy('id')->limit(1000)->get(['id', 'name', 'email', 'phone']);
        $groups = [];
        $pairs = [];
        foreach ($records as $record) {
            $name = $identity->normalizeName($kind === 'student' ? trim($record->first_name.' '.$record->last_name) : $record->name);
            if ($name === '' || str_contains((string) $record->email, '@internal.invalid')) {
                continue;
            }
            $email = $identity->normalizeEmail($record->email);
            try {
                $phone = $identity->normalizePhone($record->phone);
            } catch (\InvalidArgumentException) {
                $phone = null;
            }
            foreach (['same normalized name and email' => $email, 'same normalized name and international phone' => $phone] as $signal => $value) {
                if ($value === null) {
                    continue;
                }
                $key = $signal.'|'.$name.'|'.$value;
                if (isset($groups[$key])) {
                    $pairKey = $groups[$key].':'.$record->id;
                    $pairs[$pairKey] = ['first' => $groups[$key], 'second' => $record->id, 'signal' => $signal];
                    if (count($pairs) >= 100) {
                        return array_values($pairs);
                    }
                } else {
                    $groups[$key] = $record->id;
                }
            }
        }

        return array_values($pairs);
    }
}
