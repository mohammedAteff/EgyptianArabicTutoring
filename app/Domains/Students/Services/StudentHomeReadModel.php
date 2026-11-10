<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormAssignmentService;
use App\Domains\Lms\Services\LmsTutoringReadModel;
use App\Domains\Lms\Services\StudentLearningService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class StudentHomeReadModel
{
    public function __construct(private TimezoneService $timezones, private StudentTeachingReadModel $teaching,
        private StudentLearningService $learning, private LmsTutoringReadModel $assignments,
        private EntitlementService $entitlements, private FormAssignmentService $forms,
        private MeetingLinkService $meetings, private StudentHomeNextAction $actions,
        private StudentIntendedDestination $destinations) {}

    /** Fresh request-scoped projections only. No learning visits, notification synchronization or read marking.
     * @return array<string,mixed>
     */
    public function data(Request $request): array
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $timezone = $request->session()->get('student_display_timezone') ?: ($student->preferred_timezone ?: $this->timezones->getBusinessTimezone());
        $bookings = Booking::query()->where('student_id', $student->id)->with('sessionType')->orderBy('start_at_utc')->orderBy('id')->get();
        $confirmed = $bookings->filter(fn (Booking $booking): bool => $booking->status === 'confirmed' && $booking->end_at_utc->gt(now('UTC')));
        $nextLesson = $confirmed->first();
        $joinable = $confirmed->first(fn (Booking $booking): bool => $this->meetings->studentUrl($booking) !== null);
        $teaching = $this->teaching->overview($student, $bookings);
        $learningUnavailable = false;
        $overview = [];
        try {
            $overview = $this->learning->overview($student);
            $hub = $this->learning->hub($student, $overview);
            $assignments = $this->assignments->assignments($student, overview: $overview);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1205) {
                throw $exception;
            }
            report($exception);
            $learningUnavailable = true;
            $hub = ['continueLearning' => collect(), 'cards' => collect()];
            $assignments = collect();
        }
        $continuation = $hub['continueLearning']->first(fn (array $item): bool => ($overview['progress'][$item['course']->id]['next'] ?? null) !== null);
        $availableAssignment = $assignments->filter(fn (array $item): bool => $item['allowed'] && $item['url'] && in_array($item['group'], ['New', 'In Progress'], true))
            ->sortBy(fn (array $item): int => $item['assignment']->id)->first();
        $availableCourse = $hub['cards']->filter(fn (array $card): bool => ! $card['visited'] && $card['progress']['status'] !== 'Completed' && $card['progress']['next'] !== null)
            ->sortBy(fn (array $card): int => $card['course']->id)->first();
        $featuredLearning = $continuation ? ['title' => $continuation['lesson']->title ?? $continuation['course']->title,
            'detail' => $continuation['course']->title, 'url' => $continuation['url'], 'button' => 'Continue Learning', 'state' => __(':status · :percent% complete', ['status' => __($overview['progress'][$continuation['course']->id]['status']), 'percent' => localized_number($overview['progress'][$continuation['course']->id]['percent'])])]
            : ($availableAssignment ? ['title' => $availableAssignment['title'], 'detail' => $availableAssignment['course']->title,
                'url' => $availableAssignment['url'], 'button' => 'Open Learning', 'state' => __($availableAssignment['state'])]
                : ($availableCourse ? ['title' => $availableCourse['course']->title, 'detail' => __('Learning prepared for you.'),
                    'url' => $availableCourse['url'], 'button' => 'Start Learning', 'state' => __(':status · :percent% complete', ['status' => __($availableCourse['progress']['status']), 'percent' => localized_number($availableCourse['progress']['percent'])])] : null));
        $formSubmissions = FormSubmission::query()->where('student_id', $student->id)->with('version:id,form_id')->orderByDesc('id')->get()
            ->groupBy(fn (FormSubmission $submission): ?int => $submission->version?->form_id)->map->first();
        $forms = $this->forms->assignedTo($student)->sort(fn (Form $a, Form $b): int => strcasecmp($a->title, $b->title) ?: ($a->id <=> $b->id))->values()->map(function (Form $form) use ($formSubmissions): array {
            $submission = $formSubmissions->get($form->id);
            $outdated = $submission && (int) $submission->form_version_id !== (int) $form->published_version_id;

            return ['form' => $form, 'submission' => $submission, 'attention' => ! $submission || $submission->status !== 'submitted' || $outdated,
                'button' => $outdated ? 'Update Questionnaire' : ($submission ? 'Continue Questionnaire' : 'Start Questionnaire'),
                'state' => $outdated ? 'Update Available' : ($submission?->status === 'submitted' ? 'Completed' : ($submission ? 'In Progress' : 'Not Started')),
                'url' => route('student.forms.show', $form->slug)];
        });
        $mandatory = $forms->first(fn (array $item): bool => $item['form']->is_mandatory && $item['attention']);
        $openHomework = $teaching['homework']->whereIn('status', ['assigned', 'in_progress']);
        $dueHomework = $openHomework->first(fn ($item): bool => $item->due_date !== null && $item->due_date->toDateString() <= now($timezone)->toDateString());
        $homework = $openHomework->first();
        $feedback = $teaching['homework']->filter(fn ($item): bool => filled($item->feedback))->sortBy('id')->first();
        $resource = $teaching['assignedResources']->whereNull('reviewed_at')->sortBy('id')->first();
        $packages = StudentPackage::query()->where('student_id', $student->id)->with(['entitlements.type', 'ledgerEntries'])->orderBy('created_at')->orderBy('id')->get();
        $formats = SessionType::query()->studentDiscoverable()->where('funding_mode', 'package')->with('requiredEntitlementType')->orderBy('id')->get()
            ->filter(fn (SessionType $format): bool => $this->entitlements->canFund($student->id, $format));
        $credits = $this->entitlements->forPackages($packages);
        $expiring = collect();
        foreach ($packages as $package) {
            foreach ($this->entitlements->projection($package) as $balance) {
                $compatible = $formats->filter(fn (SessionType $format): bool => $format->requiredEntitlementType->code === $balance['code'] && $balance['available'] >= $format->required_entitlement_units);
                $expiry = $package->expiryDeadline();
                if ($compatible->isNotEmpty() && $expiry !== null && $expiry->gt(now('UTC')) && $expiry->lte(now('UTC')->addDays(14))) {
                    $expiring->push(['package' => $package, 'balance' => $balance, 'expiry' => $expiry]);
                }
            }
        }
        $expiring = $expiring->sort(fn (array $a, array $b): int => $a['expiry']->getTimestamp() <=> $b['expiry']->getTimestamp()
            ?: $a['package']->created_at->getTimestamp() <=> $b['package']->created_at->getTimestamp()
            ?: $a['package']->id <=> $b['package']->id ?: $a['balance']['id'] <=> $b['balance']['id'])->first();
        $hasCurrentHold = is_string($request->session()->get('_visitor_token')) && BookingHold::query()->active()
            ->where('visitor_token', $request->session()->get('_visitor_token'))->where('session_token', $request->session()->getId())->exists();
        $hasAppointmentContext = $bookings->contains(fn (Booking $booking): bool => $booking->status === 'completed'
            || (in_array($booking->status, ['confirmed', 'held', 'pending_confirmation'], true) && $booking->end_at_utc->gt(now('UTC'))));
        $diagnostic = ! $learningUnavailable && ! $hasAppointmentContext && ! $hasCurrentHold && $formats->isEmpty() && ! $packages->contains(fn (StudentPackage $package): bool => $this->entitlements->eligible($package)) && $hub['cards']->isEmpty()
            && $assignments->isEmpty() && $teaching['homework']->isEmpty() && $teaching['learningPlans']->isEmpty()
            && $teaching['assignedResources']->isEmpty() && $teaching['errorLog']->isEmpty() && $teaching['progress']['shared_notes'] === 0
            ? SessionType::query()->publicBookable()->where('slug', 'diagnostic-session')->first() : null;
        $practiceUrl = route('student.teaching.index');
        $lessonAction = $nextLesson ? $this->action($nextLesson->start_at_utc->lt(now('UTC')) ? 'Your lesson is in progress' : 'Your next lesson is coming up', __($nextLesson->sessionType->title ?? 'Private lesson'), 'View Session', route('student.bookings.show', $nextLesson), 'lesson:'.$nextLesson->id) : null;
        $homeworkAction = $homework ? $this->action('Continue your homework', $homework->title, 'Open Homework', $practiceUrl.'#homework-'.$homework->id, 'homework:'.$homework->id) : null;
        $learningAction = $featuredLearning ? $this->action($continuation ? 'Pick up where you left off' : 'Learning prepared for you', $featuredLearning['title'].' · '.__($featuredLearning['state']), $featuredLearning['button'], $featuredLearning['url'], 'learning') : null;
        $nextAction = $this->actions->choose([
            'join' => $joinable ? $this->action('Your lesson is ready', __($joinable->sessionType->title ?? 'Private lesson'), 'Join Lesson', $this->meetings->studentUrl($joinable), 'lesson:'.$joinable->id) : null,
            'soon' => $nextLesson && $nextLesson->start_at_utc->gte(now('UTC')) && $nextLesson->start_at_utc->lte(now('UTC')->addHours(24)) ? $lessonAction : null,
            'due_homework' => $dueHomework ? $this->action('A little practice for today', $dueHomework->title.' · '.__('Due :date', ['date' => localized_date($dueHomework->due_date, 'j M Y')]), 'Open Homework', $practiceUrl.'#homework-'.$dueHomework->id, 'homework:'.$dueHomework->id) : null,
            'mandatory_form' => $mandatory ? $this->action('Tell me about your learning', __($mandatory['form']->title), $mandatory['button'], $mandatory['url'], 'form:'.$mandatory['form']->id) : null,
            'homework' => $homeworkAction,
            'expiring' => $expiring ? $this->action('Make time for your Arabic', $expiring['package']->package_name.' · '.__($expiring['balance']['label']).' · '.__('Valid until :date', ['date' => localized_date($expiring['expiry']->setTimezone($timezone), 'j M Y, g:i A')]).' '.$timezone, 'Choose a Lesson Time', route('student.bookings.create'), 'credits') : null,
            'continuation' => $continuation ? $learningAction : null,
            'learning' => $learningAction,
            'book' => $formats->isNotEmpty() && $confirmed->isEmpty() ? $this->action('Ready for your next lesson?', __('Choose a compatible lesson format using your available credits. Times are subject to availability.'), 'Book Your Next Lesson', route('student.bookings.create'), 'credits') : null,
            'later' => $lessonAction,
            'resource' => $resource ? $this->action('Something to explore', $resource->resource->title, 'View Resource', $practiceUrl.'#resources', 'resource:'.$resource->id) : null,
            'diagnostic' => $diagnostic ? $this->action('Let’s begin with your goals', __('Start with a diagnostic and learning consultation.'), 'Book Your Diagnostic', route('booking.index'), 'diagnostic') : null,
            'feedback' => $feedback ? $this->action('Feedback available', $feedback->title, 'Review Feedback', $practiceUrl.'#homework-'.$feedback->id, 'homework:'.$feedback->id) : null,
            'fallback' => $this->action('Your learning space is here', __('Your courses and learning activities will appear in My Learning when they’re available.'), 'View My Learning', route('student.learning.index')),
        ]);
        $practice = collect();
        if ($homework && ($nextAction['source'] ?? '') !== 'homework:'.$homework->id) {
            $practice->push(['title' => $homework->title, 'state' => $homework->status === 'in_progress' ? 'In Progress' : 'Not Started', 'button' => 'Open Homework', 'url' => $practiceUrl.'#homework-'.$homework->id]);
        }
        if ($feedback && ($nextAction['source'] ?? '') !== 'homework:'.$feedback->id && $feedback->id !== $homework?->id) {
            $practice->push(['title' => $feedback->title, 'state' => 'Feedback available', 'button' => 'Review Feedback', 'url' => $practiceUrl.'#homework-'.$feedback->id]);
        }
        $updates = StudentNotification::query()->where('student_id', $student->id)->whereNull('read_at')->orderByDesc('created_at')->orderByDesc('id')->limit(3)->get()
            ->map(fn (StudentNotification $notification): array => ['title' => $notification->title, 'created_at' => $notification->created_at,
                'url' => $this->destinations->safeRelativeUrl($notification->link) ?? route('student.notifications.index')]);

        return ['student' => $student, 'timezone' => $timezone, 'nextLesson' => $nextLesson, 'nextAction' => $nextAction,
            'featuredLearning' => ($nextAction['source'] ?? '') === 'learning' ? null : $featuredLearning,
            'practice' => $practice->take(2), 'credits' => $credits, 'formats' => $formats,
            'learningUnavailable' => $learningUnavailable, 'progress' => $teaching['progress'], 'forms' => $forms, 'updates' => $updates];
    }

    /** @return array{title:string,detail:string,button:string,url:string,source:string} */
    private function action(string $title, string $detail, string $button, string $url, string $source = ''): array
    {
        return compact('title', 'detail', 'button', 'url', 'source');
    }
}
