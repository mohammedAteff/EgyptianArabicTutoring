<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Administration\Models\StaffTask;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Services\FormCompletionReadModel;
use App\Domains\Lms\Services\LmsOperationsService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramBusinessReadService
{
    public function __construct(private TimezoneService $timezones, private StudentLedgerService $ledger, private EntitlementService $entitlements,
        private FormCompletionReadModel $forms, private InstallmentScheduleService $installments) {}

    /** @return array{text: string, buttons: list<array<string, mixed>>} */
    public function schedule(int $days, int $offset, bool $personal, int $page = 0): array
    {
        $start = CarbonImmutable::now($this->timezones->getBusinessTimezone())->startOfDay()->addDays($offset);
        $query = Booking::query()->where('status', 'confirmed')->where('start_at_utc', '>=', $start->utc())->where('start_at_utc', '<', $start->addDays($days)->utc());
        $count = (clone $query)->count();
        $hours = round((float) (clone $query)->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_at_utc, end_at_utc)), 0) AS minutes')->value('minutes') / 60, 1);
        $bookings = $query->with(['student', 'contact', 'sessionType'])->orderBy('start_at_utc')->orderBy('id')->offset($page * 8)->limit(8)->get();
        $forms = $this->forms->forStudents($bookings->pluck('student_id')->filter()->unique()->all());
        $lines = ['<b>📅 '.($days > 1 ? 'Next 7 days' : ($offset ? 'Tomorrow' : 'Today')).'</b>', $start->format('D, j M Y').' · '.e($this->timezones->getBusinessTimezone()), $count.' confirmed sessions · '.$hours.' teaching hours', ''];
        $buttons = [];
        foreach ($bookings as $booking) {
            $name = $personal ? ($booking->student->name ?? $booking->contact->name ?? 'Student') : 'Lesson #'.$booking->id;
            $time = $booking->business_start->format($days > 1 ? 'D j M · H:i' : 'H:i');
            $lines[] = '<b>'.$time.' · '.e($name).'</b>';
            $lines[] = e($booking->sessionType->title ?? 'Lesson').' · '.$booking->start_at_utc->diffInMinutes($booking->end_at_utc).' min';
            if (! $booking->meeting_room_id || ! $booking->meeting_url_snapshot) {
                $lines[] = '⚠️ Meeting details missing';
            }
            $missing = $forms->get($booking->student_id, collect())->filter(fn (array $form): bool => $form['mandatory'] && ! $form['has_submitted'])->count();
            if ($missing) {
                $lines[] = '📝 '.$missing.' required form(s) outstanding';
            }
            if ($personal && $booking->student_id) {
                $buttons[] = ['label' => Str::limit($name, 30), 'command' => 'student', 'state' => ['id' => $booking->student_id]];
            }
            $lines[] = '';
        }
        if ($count === 0) {
            $lines[] = 'No confirmed lessons in this period.';
        }
        if ($page > 0) {
            $buttons[] = ['label' => '← Previous', 'state' => ['page' => $page - 1]];
        }
        if (($page + 1) * 8 < $count) {
            $buttons[] = ['label' => 'Next →', 'state' => ['page' => $page + 1]];
        }

        return ['text' => implode("\n", $lines), 'buttons' => $buttons];
    }

    /** @param array<string, mixed> $state
     * @return array{text: string, buttons: list<array<string, mixed>>}
     */
    public function student(array $state): array
    {
        $query = Student::query();
        if (isset($state['id'])) {
            $student = $query->find($state['id']);

            return $student ? $this->studentCard($student, $state['view'] ?? 'overview', (int) ($state['page'] ?? 0)) : ['text' => 'Student unavailable. Use /student to search again.', 'buttons' => []];
        }
        $name = app(StudentIdentityService::class)->normalizeName(trim((string) ($state['query'] ?? '')));
        if ($name === '') {
            return ['text' => '<b>👤 Find a student</b>'."\n".'Send /student followed by all or part of their name.', 'buttons' => []];
        }
        $exact = (clone $query)->where('name_normalized', $name)->limit(2)->get();
        if ($exact->count() === 1) {
            return $this->studentCard($exact->first(), 'overview');
        }
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $name);
        $matches = $query->where('name_normalized', 'like', '%'.$escaped.'%')->orderBy('name_normalized')->orderBy('id')->limit(9)->get();
        if ($matches->isEmpty()) {
            return ['text' => 'No student matched. Try another part of their name.', 'buttons' => []];
        }
        $buttons = [];
        foreach ($matches->take(8) as $student) {
            $buttons[] = ['label' => Str::limit($student->name, 32).' · #'.$student->id, 'state' => ['id' => $student->id, 'query' => null]];
        }

        return ['text' => '<b>👤 Choose a student</b>'."\n".'No unique student match. Select the correct name and record number.'.($matches->count() > 8 ? "\nMore matches exist; use a longer name to narrow the search." : ''), 'buttons' => $buttons];
    }

    /** @return array{text: string, buttons: list<array<string, mixed>>} */
    private function studentCard(Student $student, string $view, int $page = 0): array
    {
        $student->loadMissing(['packages.payments', 'packages.refunds', 'packages.ledgerEntries', 'packages.entitlements.type', 'packages.installments']);
        $lines = ['<b>👤 '.e($student->name).'</b>', 'Record #'.$student->id, ''];
        if ($view === 'overview') {
            $lines[] = '<b>Available to book</b>';
            foreach ($this->entitlements->forPackages($student->packages) as $balance) {
                $lines[] = e($balance['label']).': '.$balance['available'].' available · '.$balance['remaining'].' remaining';
            }
            $next = Booking::query()->where('student_id', $student->id)->where('status', 'confirmed')->where('start_at_utc', '>', now('UTC'))->orderBy('start_at_utc')->first();
            $lines[] = '';
            $lines[] = $next ? '📅 Next lesson: '.$next->business_start->format('D j M, H:i').' · '.e($this->timezones->getBusinessTimezone()) : 'No upcoming confirmed lesson.';
            $missing = $this->forms->forStudent($student)->filter(fn (array $form): bool => $form['mandatory'] && ! $form['has_submitted'])->count();
            $lines[] = '📝 Required forms outstanding: '.$missing;
        } else {
            $lines[] = '<b>'.($view === 'payments' ? '💳 Payments and balances' : '📦 Package history').'</b>';
            foreach ($student->packages->sortByDesc('id')->slice($page * 5, 5) as $package) {
                $summary = $this->ledger->summary($package, true);
                $lines[] = '';
                $lines[] = '<b>'.e($package->package_name).'</b> · '.e($summary['entitlement_status']);
                if ($view === 'payments') {
                    $lines[] = e($package->currency).' · Net paid '.$summary['net_paid'].' · Outstanding '.$summary['balance_due'];
                    foreach ($this->installments->projection($package, $summary) as $installment) {
                        if ($installment['status'] === 'overdue') {
                            $lines[] = '⚠️ Overdue: '.$installment['due'].' · due '.$installment['due_date'];
                        }
                    }
                } else {
                    foreach ($summary['entitlements'] as $allocation) {
                        $lines[] = e($allocation['label']).': '.$allocation['available'].' available · '.$allocation['remaining'].' remaining';
                    }
                    $lines[] = 'Expires: '.e(app(TelegramReportFormatter::class)->expiry($package));
                }
            }
        }
        $buttons = [
            ['label' => 'Overview', 'state' => ['id' => $student->id, 'view' => 'overview', 'page' => 0]],
            ['label' => 'Packages', 'state' => ['id' => $student->id, 'view' => 'packages', 'page' => 0]],
            ['label' => 'Payments', 'state' => ['id' => $student->id, 'view' => 'payments', 'page' => 0]],
            ['label' => 'Open student', 'url' => route('admin.students.show', $student->id)],
        ];
        if ($view !== 'overview' && $page > 0) {
            $buttons[] = ['label' => '← Previous', 'state' => ['id' => $student->id, 'page' => $page - 1]];
        }
        if ($view !== 'overview' && ($page + 1) * 5 < $student->packages->count()) {
            $buttons[] = ['label' => 'Next →', 'state' => ['id' => $student->id, 'page' => $page + 1]];
        }

        return ['text' => implode("\n", $lines), 'buttons' => $buttons];
    }

    /** @return array{text: string, buttons: list<array<string, mixed>>} */
    public function money(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $totals = [];
        foreach ([['class' => PaymentRecord::class, 'date' => 'paid_at', 'amount' => 'amount_paid', 'key' => 'received'], ['class' => PaymentRefund::class, 'date' => 'refunded_at', 'amount' => 'amount_refunded', 'key' => 'refunded']] as $type) {
            foreach ($type['class']::query()->whereBetween($type['date'], [$start, $end])->orderBy('id')->lazyById(200) as $record) {
                $currency = $record->currency;
                $totals[$currency][$type['key']] = bcadd($totals[$currency][$type['key']] ?? '0.00', (string) $record->getAttribute($type['amount']), 2);
            }
        }
        foreach (StudentPackage::query()->whereNotIn('status', ['cancelled'])->with(['payments', 'refunds', 'ledgerEntries', 'entitlements.type', 'installments'])->lazyById(100) as $package) {
            $summary = $this->ledger->summary($package, true);
            $currency = $package->currency;
            $totals[$currency]['outstanding'] = bcadd($totals[$currency]['outstanding'] ?? '0.00', $summary['balance_due'], 2);
            foreach ($this->installments->projection($package, $summary) as $installment) {
                if ($installment['status'] === 'overdue') {
                    $totals[$currency]['overdue'] = bcadd($totals[$currency]['overdue'] ?? '0.00', $installment['due'], 2);
                }
            }
        }
        $lines = ['<b>💳 Money</b>', 'Payments and refunds use their received/refunded dates.', 'Outstanding and overdue are current balances.', ''];
        ksort($totals);
        foreach ($totals as $currency => $values) {
            $received = $values['received'] ?? '0.00';
            $refunded = $values['refunded'] ?? '0.00';
            $lines[] = '<b>'.e($currency).'</b>';
            $lines[] = 'Received: '.$received.' · Refunds: '.$refunded;
            $lines[] = 'Net received: '.bcsub($received, $refunded, 2);
            $lines[] = 'Outstanding: '.($values['outstanding'] ?? '0.00').' · Overdue: '.($values['overdue'] ?? '0.00');
            $lines[] = '';
        }
        if ($totals === []) {
            $lines[] = 'No payments or balances recorded.';
        }

        return ['text' => implode("\n", $lines), 'buttons' => []];
    }

    /** @return array{text: string, buttons: list<array<string, mixed>>} */
    public function attention(bool $personal, string $category = '', int $page = 0): array
    {
        $items = ['lessons' => [], 'forms' => [], 'payments' => [], 'credits' => [], 'expiry' => [], 'learning' => [], 'assignments' => [], 'quizzes' => [], 'waitlist' => [], 'tasks' => [], 'system' => []];
        $bookings = Booking::query()->where('status', 'confirmed')->whereBetween('start_at_utc', [now('UTC'), now('UTC')->addDays(7)])->with(['student', 'contact'])->orderBy('start_at_utc')->get();
        $forms = $this->forms->forStudents($bookings->pluck('student_id')->filter()->unique()->all());
        foreach ($bookings as $booking) {
            $name = $personal ? ($booking->student->name ?? $booking->contact->name ?? 'Student') : 'Lesson #'.$booking->id;
            $label = $booking->business_start->format('D j M H:i').' · '.$name;
            $url = $personal ? ($booking->student_id ? route('admin.students.show', $booking->student_id) : route('admin.bookings.index')) : null;
            if (! $booking->meeting_room_id || ! $booking->meeting_url_snapshot) {
                $items['lessons'][] = ['label' => $label.' — meeting details missing', 'url' => $url];
            }
            foreach ($forms->get($booking->student_id, collect()) as $form) {
                if ($form['mandatory'] && ! $form['has_submitted']) {
                    $items['forms'][] = ['label' => $label.' — '.$form['title'], 'url' => $url];
                }
            }
        }
        foreach (StudentPackage::query()->with(['student', 'payments', 'refunds', 'ledgerEntries', 'entitlements.type', 'installments'])->lazyById(100) as $package) {
            $name = $personal ? ($package->student->name ?? 'Unavailable student') : 'Package #'.$package->id;
            $url = $personal ? route('admin.students.show', $package->student_id) : null;
            $summary = $this->ledger->summary($package, true);
            foreach ($this->installments->projection($package, $summary) as $installment) {
                if ($installment['status'] === 'overdue') {
                    $items['payments'][] = ['label' => $name.' — '.($personal ? $installment['due'].' '.$package->currency.' · ' : '').'due '.$installment['due_date'], 'url' => $url];
                }
            }
            if (! $this->entitlements->eligible($package)) {
                continue;
            }
            foreach ($summary['entitlements'] as $allocation) {
                if ($allocation['id'] === null) {
                    continue;
                }
                if ($allocation['available'] <= 1) {
                    $items['credits'][] = ['label' => $name.' — '.$allocation['label'].': '.$allocation['available'].' available', 'url' => $url];
                }
                if ($allocation['available'] > 0 && $package->expiryDeadline()?->lte(now('UTC')->addDays(14))) {
                    $items['expiry'][] = ['label' => $name.' — '.$allocation['label'].' · '.app(TelegramReportFormatter::class)->expiry($package), 'url' => $url];
                }
            }
        }
        $homework = Homework::query()->where('status', 'submitted')->orderBy('id')->get(['id', 'title', 'student_id']);
        foreach ($homework as $record) {
            $items['learning'][] = ['label' => 'Homework awaiting feedback'.($personal ? ' — '.$record->title : ''), 'url' => $personal ? route('admin.students.teaching', $record->student_id) : null];
        }
        $reviews = app(LmsOperationsService::class)->reviews([], $page + 1, 8);
        foreach (['assignments', 'quizzes'] as $kind) {
            foreach ($reviews[$kind]->items() as $submission) {
                $items[$kind][] = ['label' => ($personal ? ($submission->student->name ?? 'Student') : 'Submission #'.$submission->id).' · '.($kind === 'quizzes' ? 'Quiz attempt' : 'Assignment').' #'.$submission->number,
                    'url' => $personal ? route('admin.students.teaching', $submission->student_id) : null];
            }
        }
        foreach (BookingWaitlist::query()->where('status', 'active')->whereNotNull('availability_notified_at')->where('date_to', '>=', now($this->timezones->getBusinessTimezone())->toDateString())->with('student')->orderBy('id')->get() as $waitlist) {
            $items['waitlist'][] = ['label' => ($personal ? ($waitlist->student->name ?? 'Student') : 'Preference #'.$waitlist->id).' — availability was offered; review current slots', 'url' => $personal ? route('admin.students.show', $waitlist->student_id) : null];
        }
        foreach (StaffTask::query()->whereIn('status', ['open', 'in_progress'])->where('due_date', '<', now($this->timezones->getBusinessTimezone())->toDateString())->orderBy('due_date')->orderBy('id')->get(['id', 'title', 'due_date']) as $task) {
            $items['tasks'][] = ['label' => ($personal ? $task->title : 'Task #'.$task->id).' · due '.$task->due_date->format('j M Y'), 'url' => $personal ? route('admin.tasks.index') : null];
        }
        foreach (AdminNotification::query()->unread()->where('type', 'system_warning')->orderBy('id')->pluck('id') as $id) {
            $items['system'][] = ['label' => 'Unresolved system warning #'.$id.' — review details in admin', 'url' => route('admin.dashboard')];
        }
        $labels = ['lessons' => 'Lessons missing meeting details · next 7 days', 'forms' => 'Missing forms · lesson/form pairs · next 7 days', 'payments' => 'Overdue installments', 'credits' => 'Low credit allocations · ≤1', 'expiry' => 'Unused allocations expiring · next 14 days', 'learning' => 'Homework awaiting feedback', 'assignments' => 'Assignment submissions awaiting review', 'quizzes' => 'Quiz attempts awaiting review', 'waitlist' => 'Active waitlist preferences previously offered availability', 'tasks' => 'Overdue open tasks', 'system' => 'Unread system warnings'];
        $counts = array_map('count', $items);
        $counts['assignments'] = $reviews['assignments']->total();
        $counts['quizzes'] = $reviews['quizzes']->total();
        $lines = ['<b>🔔 Needs attention</b>', e($this->timezones->getBusinessTimezone()), ''];
        $buttons = [];
        if (isset($items[$category])) {
            $lines[] = '<b>'.e($labels[$category]).' — '.$counts[$category].'</b>';
            $visible = in_array($category, ['assignments', 'quizzes'], true) ? $items[$category] : array_slice($items[$category], $page * 8, 8);
            foreach ($visible as $item) {
                $label = $item['label'];
                $lines[] = '• '.e($label);
                if ($item['url']) {
                    $buttons[] = ['label' => Str::limit($label, 30), 'url' => $item['url']];
                }
            }
            if ($page > 0) {
                $buttons[] = ['label' => '← Previous', 'state' => ['page' => $page - 1]];
            }
            if (($page + 1) * 8 < $counts[$category]) {
                $buttons[] = ['label' => 'Next →', 'state' => ['page' => $page + 1]];
            }
            $buttons[] = ['label' => 'All categories', 'state' => ['category' => '', 'page' => 0]];
        } else {
            foreach ($items as $key => $rows) {
                $lines[] = $counts[$key].' · '.e($labels[$key]);
                $buttons[] = ['label' => Str::limit($labels[$key], 28).' ('.$counts[$key].')', 'state' => ['category' => $key, 'page' => 0]];
            }
        }

        return ['text' => implode("\n", $lines), 'buttons' => $buttons];
    }

    /** @return array{text: string, buttons: list<array<string, mixed>>} */
    public function status(): array
    {
        $lines = ['<b>🛠 System status</b>', 'Checked: '.now($this->timezones->getBusinessTimezone())->format('j M Y H:i').' · '.e($this->timezones->getBusinessTimezone()), ''];
        foreach (['Scheduler' => Setting::get('last_scheduler_run_at'), 'Telegram monitor' => Setting::get('telegram.last_tick_at'), 'Watchdog' => Setting::get('telegram.last_watchdog_run_at'), 'Queue worker' => Cache::get('queue_worker_heartbeat_at')] as $label => $heartbeat) {
            try {
                $at = $heartbeat ? CarbonImmutable::parse((string) $heartbeat) : null;
                $lines[] = ($at && $at->gt(now('UTC')->subMinutes(15)) ? '✅ ' : '⚠️ ').$label.': '.($at ? $at->setTimezone($this->timezones->getBusinessTimezone())->format('j M H:i') : 'Not observed');
            } catch (\Throwable) {
                $lines[] = '⚠️ '.$label.': Invalid heartbeat';
            }
        }
        $backup = (string) Setting::get('last_backup_status', 'unknown');
        $lines[] = 'Local backup: '.(str_starts_with($backup, 'success') ? '✅ Last recorded result succeeded' : '⚠️ Not successful or unknown; review in admin');
        $offsite = (string) Setting::get('last_offsite_backup_status', 'unknown');
        $lines[] = 'Last recorded offsite result: '.(in_array($offsite, ['success', 'failed'], true) ? $offsite : 'Not established / unknown');
        $lines[] = 'Maintenance: '.(filter_var(Setting::get('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN) ? 'On' : 'Off');

        return ['text' => implode("\n", $lines), 'buttons' => []];
    }

    public function briefing(string $mode, bool $personal): string
    {
        $schedule = $this->schedule(1, $mode === 'wrap' ? 1 : 0, $personal);
        $attention = $this->attention($personal);
        $lines = ['<b>'.($mode === 'wrap' ? '🌙 Daily wrap-up · tomorrow’s preparation' : '☀️ Morning briefing').'</b>', '', $schedule['text'], '', $attention['text']];
        if ($personal) {
            $from = CarbonImmutable::now($this->timezones->getBusinessTimezone())->startOfDay()->utc();
            $lines[] = '';
            $lines[] = $this->money($from, CarbonImmutable::now('UTC'))['text'];
        }

        return implode("\n", $lines);
    }

    public function weekly(bool $personal): string
    {
        $through = CarbonImmutable::now($this->timezones->getBusinessTimezone())->startOfDay();
        $from = $through->subDays(7)->utc();
        $to = $through->utc()->subMicrosecond();
        $query = Booking::query()->whereBetween('start_at_utc', [$from, $to]);
        $lines = ['<b>📆 Weekly business summary</b>', $through->subDays(7)->format('j M').' – '.$through->subDay()->format('j M Y').' · '.e($this->timezones->getBusinessTimezone()),
            'Completed lessons: '.(clone $query)->where('status', 'completed')->count(), 'No-shows: '.(clone $query)->where('status', 'no_show')->count(), ''];
        if ($personal) {
            $lines[] = $this->money($from, $to)['text'];
        }
        $lines[] = $this->attention($personal)['text'];

        return implode("\n", $lines);
    }
}
