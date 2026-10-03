<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MeetingRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chronological_rotation_uses_each_room_and_never_reuses_consecutive_rooms(): void
    {
        $rooms = $this->rooms(3);
        $links = app(MeetingLinkService::class);
        $ids = [];
        foreach (['10:00', '11:00', '12:00', '13:00'] as $time) {
            $ids[] = $links->assign($this->booking($time))->meeting_room_id;
        }
        $this->assertSame([$rooms[0]->id, $rooms[1]->id, $rooms[2]->id, $rooms[0]->id], $ids);
    }

    public function test_inserting_a_booking_checks_both_neighbors_and_single_room_fails_closed(): void
    {
        $rooms = $this->rooms(2);
        $links = app(MeetingLinkService::class);
        $first = $links->assign($this->booking('10:00'));
        $last = $links->assign($this->booking('12:00'));
        $middle = $links->assign($this->booking('11:00'));
        $this->assertNull($middle->meeting_room_id);
        $third = $this->rooms(1)[0];
        $this->assertSame($third->id, $links->assign($middle)->meeting_room_id);
        $this->assertNotSame($first->meeting_room_id, $last->meeting_room_id);
    }

    public function test_manual_assignment_retains_valid_snapshot_and_revalidation_reassigns_disabled_room(): void
    {
        $rooms = $this->rooms(2);
        $links = app(MeetingLinkService::class);
        $booking = $links->assign($this->booking('10:00'), roomId: $rooms[1]->id);
        $snapshot = $booking->meeting_url_snapshot;
        $rooms[1]->update(['url' => 'https://example.org/changed']);
        $this->assertSame($snapshot, $links->revalidate($booking)->meeting_url_snapshot);
        $rooms[1]->update(['active' => false]);
        $this->assertSame($rooms[0]->id, $links->revalidate($booking)->meeting_room_id);
    }

    public function test_preferred_provider_wins_and_overlap_manual_override_is_rejected(): void
    {
        $this->rooms(1);
        $preferred = MeetingProvider::create(['name' => 'Preferred QA', 'active' => true]);
        $room = MeetingRoom::create(['meeting_provider_id' => $preferred->id, 'name' => 'Preferred room', 'url' => 'https://example.org/preferred', 'url_hash' => hash('sha256', 'https://example.org/preferred'), 'active' => true]);
        $student = Student::factory()->verified()->create(['preferred_meeting_provider_id' => $preferred->id]);
        $booking = $this->booking('10:00');
        $booking->update(['student_id' => $student->id]);
        $links = app(MeetingLinkService::class);
        $this->assertSame($room->id, $links->assign($booking)->meeting_room_id);
        $this->expectException(ValidationException::class);
        $links->assign($this->booking('10:30'), roomId: $room->id);
    }

    public function test_reconcile_only_fills_future_unassigned_lessons_without_touching_existing_snapshots(): void
    {
        $this->rooms(2);
        $links = app(MeetingLinkService::class);
        $assigned = $links->assign($this->booking('10:00'));
        $pending = $this->booking('11:00');
        $cancelled = $this->booking('12:00');
        $cancelled->update(['status' => 'cancelled']);
        $this->assertSame(['assigned' => 1, 'needed' => 0], $links->reconcileUpcoming());
        $this->assertNotNull($pending->fresh()->meeting_room_id);
        $this->assertNull($cancelled->fresh()->meeting_room_id);
        $this->assertSame($assigned->meeting_url_snapshot, $assigned->fresh()->meeting_url_snapshot);
    }

    private function rooms(int $count): array
    {
        $provider = MeetingProvider::where('is_default', true)->firstOrFail();
        $rooms = [];
        for ($i = 0; $i < $count; $i++) {
            $url = 'https://example.org/room-'.Str::uuid();
            $rooms[] = MeetingRoom::create(['meeting_provider_id' => $provider->id, 'name' => 'QA room '.$i, 'url' => $url, 'url_hash' => hash('sha256', $url), 'active' => true]);
        }

        return $rooms;
    }

    private function booking(string $time): Booking
    {
        $contact = Contact::firstOrCreate(['email' => 'room-expansion@example.org'], ['name' => 'Room QA']);
        $type = SessionType::firstOrCreate(['slug' => 'expansion-qa'], ['title' => 'Expansion QA', 'duration_minutes' => 60, 'price' => '25.00', 'currency' => 'USD', 'active' => true]);
        $instant = CarbonImmutable::parse('2027-03-03 '.$time, 'UTC');

        return Booking::create(['contact_id' => $contact->id, 'session_type_id' => $type->id, 'start_at_utc' => $instant, 'end_at_utc' => $instant->addHour(), 'status' => 'confirmed', 'confirmation_token' => Str::random(64), 'idempotency_key' => Str::random(48)] + app(TimezoneService::class)->createBookingSnapshot($instant, $instant->addHour(), 'Africa/Cairo', 'Africa/Cairo'));
    }
}
