<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Lms\Services\LmsLearningNotifications;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\LearningPlan;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\ResourceCollectionAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentErrorLog;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentProgressReport;
use App\Domains\Students\Models\StudentSkillAssessment;
use App\Domains\Students\Models\TeachingTag;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentTeachingReadModel
{
    public function __construct(private StudentNotificationService $notifications, private EntitlementService $entitlements, private TimezoneService $timezones, private LmsLearningNotifications $learningNotifications) {}

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
    public function data(Student $student, Collection $bookings, Collection $packages, Collection $forms, Collection $submissions, bool $readOnly = false): array
    {
        $homework = $this->shared(Homework::query(), $student)->with([
            'submissions', 'resource:id,title,status,published_at', 'material' => fn ($query) => $query->visibleToStudent()->whereHas('booking', fn ($bookings) => $bookings->where('student_id', $student->id))->select(['id', 'booking_id', 'title']),
        ])->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->get();
        $plans = $this->shared(LearningPlan::query(), $student, false)->with('milestones')->orderByDesc('id')->get();
        $resources = $this->resources($student, studentDiscovery: true);
        $errors = $this->shared(StudentErrorLog::query(), $student)->orderByDesc('id')->get();
        $tags = $this->shared(TeachingTag::query(), $student)->orderBy('label')->orderBy('id')->get();
        $notesCount = StudentBin::query()->where('student_id', $student->id)->where('student_visible', true)->count();
        $milestones = $plans->flatMap(fn (LearningPlan $plan) => $plan->milestones);
        $openHomework = $homework->whereIn('status', ['assigned', 'in_progress'])->first();
        $outstandingForm = $forms->sortByDesc('is_mandatory')->first(fn ($form): bool => ! $submissions->get($form->id)
            || $submissions->get($form->id)->status !== 'submitted'
            || (int) $submissions->get($form->id)->form_version_id !== (int) $form->published_version_id);
        $unreviewed = $resources->whereNull('reviewed_at')->first();
        $canBook = false;
        $requirements = SessionType::query()->studentDiscoverable()->where('funding_mode', 'package')
            ->where('required_entitlement_units', '>=', 1)->whereHas('requiredEntitlementType', fn ($types) => $types->where('active', true))
            ->get(['required_entitlement_type_id', 'required_entitlement_units']);
        $purchaseReferences = app(StudentFinancialReferences::class)->forStudent($student->id)['purchases'];
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
                $expiry = $package->expiryDeadline();
                $soon = $package->status === 'active' && $expiry !== null && $expiry->lessThanOrEqualTo(now($this->timezones->getBusinessTimezone())->addDays(14)) && $balance['remaining'] > 0;
                $low = $this->entitlements->eligible($package) && $balance['available'] <= 2;
                if (! $low && ! $soon) {
                    continue;
                }
                $expiryLabel = $package->expires_at
                    ? $package->expires_at->setTimezone($student->preferred_timezone ?: $this->timezones->getBusinessTimezone())->format('j M Y, g:i:s A T')
                    : $package->expiryLabel();
                $message = $balance['label'].' · '.$package->package_name.' · Purchase #'.$package->id.' · '.$balance['available'].' available · Valid until: '.$expiryLabel;
                $notices[] = str_replace('Purchase #'.$package->id.' ·', 'Purchase #'.$purchaseReferences[$package->id].' ·', $message);
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
                'type' => 'lesson', 'title' => 'Lesson Update', 'message' => 'Session #'.$booking->id.' · '.app(StudentPortalService::class)->bookingStatusLabel($booking).'. Check your session details for the current date and time.', 'link' => route('student.bookings.show', $booking->id, false)];
            foreach ($booking->lessonMaterials as $material) {
                $events[] = ['key' => 'material:'.$material->id, 'type' => 'material', 'title' => 'Lesson material published', 'message' => 'Shared material is available in your lesson workspace.', 'link' => route('student.lessons.show', $booking->id, false)];
            }
        }
        if (! $readOnly) {
            $this->notifications->synchronize($student, array_merge($events, $this->learningNotifications->facts($student)));
        }
        $action = app(StudentNextActionService::class)->choose($bookings, $packages, $outstandingForm, $openHomework, $unreviewed, $canBook, $requirements);

        return [
            'homework' => $homework, 'learningPlans' => $plans, 'assignedResources' => $resources, 'errorLog' => $errors, 'teachingTags' => $tags,
            'progress' => ['completed_lessons' => $bookings->where('status', 'completed')->count(), 'milestones_completed' => $milestones->where('status', 'completed')->count(), 'milestones_total' => $milestones->count(), 'shared_notes' => $notesCount, 'homework_completed' => $homework->where('status', 'completed')->count(), 'homework_total' => $homework->count(), 'errors_improved' => $errors->whereIn('status', ['improved', 'resolved'])->count(), 'errors_total' => $errors->count()],
            'nextAction' => $action, 'entitlementNotices' => $notices,
            'canBookWithPackage' => $canBook,
            'skillAssessments' => StudentSkillAssessment::query()->where('student_id', $student->id)->where('student_visible', true)->with('skill')->orderByDesc('assessed_at')->get(),
            'progressReports' => StudentProgressReport::query()->where('student_id', $student->id)->where('student_visible', true)->orderByDesc('date_to')->get(),
            'resourceCollections' => ResourceCollectionAssignment::query()->where('student_id', $student->id)->where('student_visible', true)->whereHas('collection', fn ($query) => $query->published())->with(['collection.resources' => fn ($query) => $query->published()->where('student_discoverable', true)])->get(),
            'unreadNotifications' => StudentNotification::query()->where('student_id', $student->id)->whereNull('read_at')->count(),
        ];
    }

    /** @return Collection<int, ResourceAssignment> */
    public function resources(Student $student, bool $studentDiscovery = false): Collection
    {
        return $this->shared(ResourceAssignment::query(), $student)->whereHas('resource', fn (Builder $query) => $query->published()->when($studentDiscovery, fn (Builder $query) => $query->where('student_discoverable', true)))
            ->with('resource:id,title,short_description')->orderByDesc('id')->get();
    }
}
