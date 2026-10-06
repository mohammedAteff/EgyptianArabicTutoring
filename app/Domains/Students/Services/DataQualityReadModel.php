<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingPolicyService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
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
            'studentDuplicates' => $this->duplicates('student'), 'contactDuplicates' => $this->duplicates('contact')];
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
