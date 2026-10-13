<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\Forms\Models\Form;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Support\Collection;

class StudentNextActionService
{
    public function __construct(private MeetingLinkService $meetings, private EntitlementService $entitlements) {}

    /** @param Collection<int, Booking> $bookings
     * @param  Collection<int, StudentPackage>  $packages
     * @param  Collection<int, SessionType>  $requirements
     * @return array{label: string, detail: string, button?: string, url: string}
     */
    public function choose(Collection $bookings, Collection $packages, ?Form $form, ?Homework $homework, ?ResourceAssignment $resource, bool $canBook, Collection $requirements): array
    {
        $upcoming = $bookings->whereIn('status', ['confirmed', 'held', 'pending'])->filter(fn (Booking $booking): bool => $booking->end_at_utc->isFuture())->sortBy('start_at_utc');
        foreach ($upcoming as $booking) {
            if ($url = $this->meetings->studentUrl($booking)) {
                return ['label' => 'Your Lesson Is Ready to Join', 'detail' => 'Open your assigned meeting room for lesson #'.$booking->id.'.', 'button' => 'Join Lesson', 'url' => $url];
            }
        }
        if ($form) {
            $label = match ($form->slug) {
                'short-form' => 'Finish Your Learning Goals', 'long-form' => 'Finish Your Learning Profile', default => 'Finish Your Questionnaire'
            };

            return ['label' => $label, 'detail' => $form->title, 'button' => 'Continue Questionnaire', 'url' => route('student.forms.show', $form->slug)];
        }
        if ($homework) {
            return ['label' => 'Continue your homework', 'detail' => $homework->title, 'url' => route('student.teaching.index').'#homework-'.$homework->id];
        }
        if ($resource) {
            return ['label' => 'Review your assigned resource', 'detail' => $resource->resource->title, 'url' => route('student.teaching.index').'#resources'];
        }
        if ($canBook) {
            $expiring = $packages->first(function (StudentPackage $package) use ($requirements): bool {
                if (! $this->entitlements->eligible($package) || ! $package->expiryDeadline()?->lessThanOrEqualTo(now('UTC')->addDays(14))) {
                    return false;
                }
                foreach ($this->entitlements->projection($package) as $balance) {
                    $allocation = $package->entitlements->firstWhere('id', $balance['id']);
                    if ($allocation && $requirements->contains(fn (SessionType $session): bool => (int) $session->required_entitlement_type_id === (int) $allocation->entitlement_type_id && (int) $session->required_entitlement_units > 0 && $balance['available'] >= (int) $session->required_entitlement_units)) {
                        return true;
                    }
                }

                return false;
            });
            if ($expiring) {
                return ['label' => 'Schedule Your Remaining Credits', 'detail' => $expiring->package_name.' is approaching its expiry date.', 'button' => 'Choose a Lesson Time', 'url' => route('student.bookings.create')];
            }
            if ($upcoming->isEmpty()) {
                return ['label' => 'Book Your Next Lesson', 'detail' => 'Choose a lesson using your eligible credits.', 'url' => route('student.bookings.create')];
            }
        }
        $balances = $this->entitlements->forPackages($packages);
        if ($packages->contains(fn (StudentPackage $package): bool => $package->identity_state === 'mapped' && ! $package->isPaused() && ($package->validity_days === null || $package->expiryDeadline() !== null)) && array_sum(array_column($balances, 'available')) === 0) {
            return ['label' => 'Plan Your Next Package', 'detail' => 'Contact your tutor to discuss continuing lessons and renewal.', 'button' => 'View Package Options', 'url' => route('home').'#pricing'];
        }

        return ['label' => 'Nothing Needs Your Attention Right Now', 'detail' => 'Your upcoming lessons and learning activity will appear below.', 'button' => 'View My Learning', 'url' => route('student.learning.index')];
    }
}
