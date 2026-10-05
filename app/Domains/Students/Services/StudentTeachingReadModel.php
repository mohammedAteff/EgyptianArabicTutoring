<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\LearningPlan;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentErrorLog;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\TeachingTag;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentTeachingReadModel
{
    public function __construct(private StudentNotificationService $notifications, private EntitlementService $entitlements, private TimezoneService $timezones) {}

    /** @template T of \Illuminate\Database\Eloquent\Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    public function shared(Builder $query, Student $student, bool $lesson = true): Builder
    {
        $query->where('student_id', $student->id)->where('student_visible', true);
        if ($lesson) {
            $query->where(fn (Builder $context) => $context->whereNull('booking_id')
                ->orWhereHas('booking', fn (Builder $bookings) => $bookings->where('student_id', $student->id)));
        }

        return $query;
    }

    /** @param Collection<int, Booking> $bookings
     * @param  Collection<int, StudentPackage>  $packages
     * @param  Collection<int, Form>  $forms
     * @param  Collection<int, FormSubmission>  $submissions
     * @return array<string, mixed>
     */
    public function data(Student $student, Collection $bookings, Collection $packages, Collection $forms, Collection $submissions): array
    {
        $homework = $this->shared(Homework::query(), $student)->with([
            'resource:id,title,status,published_at', 'material' => fn ($query) => $query->visibleToStudent()->whereHas('booking', fn ($bookings) => $bookings->where('student_id', $student->id))->select(['id', 'booking_id', 'title']),
        ])->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->get();
        $plans = $this->shared(LearningPlan::query(), $student, false)->with('milestones')->orderByDesc('id')->get();
        $resources = $this->shared(ResourceAssignment::query(), $student)->whereHas('resource', fn (Builder $query) => $query->published())
            ->with('resource:id,title,short_description')->orderByDesc('id')->get();
        $errors = $this->shared(StudentErrorLog::query(), $student)->orderByDesc('id')->get();
        $tags = $this->shared(TeachingTag::query(), $student)->orderBy('label')->orderBy('id')->get();
        $notesCount = StudentBin::query()->where('student_id', $student->id)->where('student_visible', true)->count();
        $milestones = $plans->flatMap(fn (LearningPlan $plan) => $plan->milestones);
        $openHomework = $homework->whereIn('status', ['assigned', 'in_progress'])->first();
        $outstandingForm = $forms->sortByDesc('is_mandatory')->first(fn ($form): bool => ! $submissions->get($form->id)
            || $submissions->get($form->id)->status !== 'submitted'
            || (int) $submissions->get($form->id)->form_version_id !== (int) $form->published_version_id);
        $unreviewed = $resources->whereNull('reviewed_at')->first();
        $hasUpcoming = $bookings->contains(fn (Booking $booking): bool => $booking->status === 'confirmed' && $booking->start_at_utc->isFuture());
        $canBook = false;
        $requirements = SessionType::query()->where('active', true)->where('funding_mode', 'package')
            ->get(['required_entitlement_type_id', 'required_entitlement_units']);
        $notices = [];
        $events = [];
        $teachingLink = route('student.teaching.index', [], false);
        foreach ($packages as $package) {
            foreach ($this->entitlements->projection($package) as $balance) {
                if ($balance['id'] === null) {
                    continue;
                }
                $allocation = $package->entitlements->firstWhere('id', $balance['id']);
                $canBook = $canBook || $requirements->contains(fn ($session): bool => (int) $session->required_entitlement_type_id === (int) $allocation?->entitlement_type_id && $balance['available'] >= (int) $session->required_entitlement_units);
                $expiry = $package->expiration_date?->toDateString();
                $soon = $package->status === 'active' && $expiry !== null && $expiry <= now($this->timezones->getBusinessTimezone())->addDays(14)->toDateString() && $balance['remaining'] > 0;
                $low = $this->entitlements->eligible($package) && $balance['available'] <= 2;
                if (! $low && ! $soon) {
                    continue;
                }
                $message = $balance['label'].' · '.$package->package_name.' · Purchase #'.$package->id.' · '.$balance['available'].' available · Expiry '.($expiry ?? 'No expiry');
                $notices[] = $message;
                $events[] = ['key' => 'entitlement:'.$balance['id'].':'.$balance['available'].':'.$expiry.':'.($soon ? 'expiry' : 'low'), 'type' => 'entitlement', 'title' => $soon ? 'Package expiry notice' : 'Low entitlement balance', 'message' => $message, 'link' => route('student.dashboard', [], false).'#credits-title'];
            }
        }
        foreach ($homework as $item) {
            $events[] = ['key' => 'homework:'.$item->id, 'type' => 'homework', 'title' => 'Homework assigned', 'message' => 'Your tutor has shared homework. Open your learning area to review it.', 'link' => $teachingLink.'#homework-'.$item->id];
        }
        foreach ($resources as $item) {
            $events[] = ['key' => 'resource:'.$item->id, 'type' => 'resource', 'title' => 'Resource assigned', 'message' => 'A learning resource is ready to review.', 'link' => $teachingLink.'#resources'];
        }
        foreach ($forms as $form) {
            $events[] = ['key' => 'form:'.$student->id.':'.$form->published_version_id, 'type' => 'form', 'title' => 'Questionnaire available', 'message' => 'Review your assigned questionnaire and save your responses.', 'link' => route('student.forms.show', $form->slug, false)];
        }
        foreach ($bookings as $booking) {
            $events[] = ['key' => 'lesson:'.$booking->id.':'.$booking->status.':'.$booking->start_at_utc->timestamp.':'.(int) $booking->admin_reconfirmation_needed,
                'type' => 'lesson', 'title' => 'Lesson update', 'message' => 'Lesson #'.$booking->id.' · '.$booking->studentStatusLabel().'. Check your sessions for the current time.', 'link' => route('student.dashboard', [], false).'#history-title'];
            foreach ($booking->lessonMaterials as $material) {
                $events[] = ['key' => 'material:'.$material->id, 'type' => 'material', 'title' => 'Lesson material published', 'message' => 'Shared material is available in your lesson workspace.', 'link' => route('student.lessons.show', $booking->id, false)];
            }
        }
        $this->notifications->synchronize($student, $events);
        $action = $outstandingForm ? ['label' => 'Finish your questionnaire', 'detail' => $outstandingForm->title, 'url' => route('student.forms.show', $outstandingForm->slug)]
            : ($openHomework ? ['label' => 'Continue your homework', 'detail' => $openHomework->title, 'url' => route('student.teaching.index').'#homework-'.$openHomework->id]
            : ($unreviewed ? ['label' => 'Review your assigned resource', 'detail' => $unreviewed->resource->title, 'url' => route('student.teaching.index').'#resources']
            : (! $hasUpcoming && $canBook ? ['label' => 'Book your next session', 'detail' => 'Use a compatible package entitlement.', 'url' => route('student.bookings.create')]
            : ['label' => 'Review your learning progress', 'detail' => 'Your tutor’s plans, notes and practice goals.', 'url' => route('student.teaching.index')])));

        return [
            'homework' => $homework, 'learningPlans' => $plans, 'assignedResources' => $resources, 'errorLog' => $errors, 'teachingTags' => $tags,
            'progress' => ['completed_lessons' => $bookings->where('status', 'completed')->count(), 'milestones_completed' => $milestones->where('status', 'completed')->count(), 'milestones_total' => $milestones->count(), 'shared_notes' => $notesCount, 'homework_completed' => $homework->where('status', 'completed')->count(), 'homework_total' => $homework->count(), 'errors_improved' => $errors->whereIn('status', ['improved', 'resolved'])->count(), 'errors_total' => $errors->count()],
            'nextAction' => $action, 'entitlementNotices' => $notices,
            'canBookWithPackage' => $canBook,
            'unreadNotifications' => StudentNotification::query()->where('student_id', $student->id)->whereNull('read_at')->count(),
        ];
    }
}
