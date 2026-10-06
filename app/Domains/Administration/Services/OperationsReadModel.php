<?php

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffBin;
use App\Domains\Booking\Models\Booking;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentOperationalAlert;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class OperationsReadModel
{
    public function __construct(private TimezoneService $timezones, private StaffTaskQuery $tasks, private EntitlementService $entitlements, private StudentLedgerService $ledger) {}

    /** @return array<string, mixed> */
    public function forAdministrator(Administrator $administrator): array
    {
        $timezone = $this->timezones->getBusinessTimezone();
        $now = CarbonImmutable::now('UTC');
        $day = $now->setTimezone($timezone)->startOfDay();
        $today = Booking::query()->with(['student', 'contact', 'sessionType'])->where('start_at_utc', '>=', $day->utc())->where('start_at_utc', '<', $day->addDay()->utc())->whereIn('status', ['confirmed', 'completed', 'no_show'])->orderBy('start_at_utc');
        $upcoming = Booking::query()->with(['student', 'contact', 'sessionType'])->where('status', 'confirmed')->where('start_at_utc', '>=', $day->addDay()->utc())->where('start_at_utc', '<', $day->addDays(8)->utc())->orderBy('start_at_utc');
        $overdue = $this->tasks->query($administrator, ['due' => 'overdue'])->where(fn (Builder $tasks) => $tasks->whereNull('student_id')->orWhereHas('student', fn (Builder $students) => $students->where('operational_status', 'active')));
        $followUps = $this->tasks->query($administrator, ['due' => 'today'])->whereIn('status', ['open', 'in_progress'])->whereHas('student', fn (Builder $students) => $students->where('operational_status', 'active'));
        $alerts = StudentOperationalAlert::query()->with('student')->whereHas('student', fn (Builder $students) => $students->where('identity_status', '!=', 'merged')->where('operational_status', 'active'))->where('status', 'active')->orderByDesc('updated_at');
        $sharedNotes = StaffBin::query()->with('pinnedBy')->where('pinned', true)->orderByDesc('pinned_at');
        $data = ['businessTz' => $timezone, 'todayDate' => $day->format('l, j F Y'), 'counts' => []];
        foreach (['todayLessons' => $today, 'upcomingLessons' => $upcoming, 'overdueTasks' => $overdue, 'followUps' => $followUps, 'activeAlerts' => $alerts, 'sharedNotes' => $sharedNotes] as $key => $query) {
            $data['counts'][$key] = (clone $query)->count();
            $data[$key] = $query->limit(12)->get();
        }
        if ($administrator->isAdmin()) {
            $expiring = StudentPackage::query()->with(['student', 'ledgerEntries', 'entitlements.type'])->whereHas('student', fn (Builder $students) => $students->where('operational_status', 'active'))->whereDate('expiration_date', '<=', $day->addDays(7)->toDateString())->orderBy('expiration_date');
            $this->entitlements->scopeAvailablePackages($expiring);
            $payments = StudentPackage::query()->with(['student', 'payments', 'refunds', 'ledgerEntries', 'entitlements.type'])->whereHas('student', fn (Builder $students) => $students->where('operational_status', 'active'))->where('status', 'active')
                ->whereRaw('final_price > (select coalesce(sum(amount_paid),0) from payment_records where student_package_id = student_packages.id) - (select coalesce(sum(amount_refunded),0) from payment_refunds where student_package_id = student_packages.id)')->orderBy('id');
            $lowCredit = Student::tutoringRoster()->where('operational_status', 'active')->with(['packages.ledgerEntries', 'packages.entitlements.type'])
                ->whereRaw('exists (select 1 from student_package_entitlements a join student_packages p on p.id = a.student_package_id join entitlement_types t on t.id = a.entitlement_type_id left join session_ledger_entries l on l.student_package_entitlement_id = a.id where p.student_id = students.id and p.status = ? and p.identity_state <> ? and t.active = 1 and ((p.expiration_date is null and p.validity_days is null) or p.expiration_date >= ?) group by a.entitlement_type_id having coalesce(sum(l.credit_change),0) between 0 and 2)', ['active', 'legacy_unclassified', $day->toDateString()])->orderBy('name_normalized');
            $forms = FormSubmission::query()->with(['student', 'version.form'])->whereHas('student', fn (Builder $students) => $students->where('operational_status', 'active'))->where('status', 'submitted')->where('submitted_at', '>=', $now->subDays(7))->orderByDesc('submitted_at');
            foreach (['expiringPackages' => $expiring, 'paymentFollowUps' => $payments, 'lowCreditStudents' => $lowCredit, 'recentForms' => $forms] as $key => $query) {
                $data['counts'][$key] = (clone $query)->count();
                $data[$key] = $query->limit(12)->get();
            }
            $data['paymentBalances'] = $data['paymentFollowUps']->mapWithKeys(fn (StudentPackage $package): array => [$package->id => $this->ledger->summary($package, true, $timezone)['balance_due']])->all();
            $data['studentCredits'] = $data['lowCreditStudents']->mapWithKeys(fn (Student $student): array => [$student->id => $this->entitlements->forPackages($student->packages)])->all();
        }

        return $data;
    }
}
