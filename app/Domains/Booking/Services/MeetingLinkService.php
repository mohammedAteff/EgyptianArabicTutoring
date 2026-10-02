<?php

namespace App\Domains\Booking\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MeetingLinkService
{
    public function current(): ?string
    {
        $url = Setting::get('video_meeting_url');

        return is_string($url) && Str::isUrl(trim($url), ['https']) ? trim($url) : null;
    }

    public function assign(Booking $booking, ?int $providerId = null, ?int $roomId = null, ?int $actorId = null): Booking
    {
        return DB::transaction(function () use ($booking, $providerId, $roomId, $actorId): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($locked->status !== 'confirmed' || $locked->end_at_utc->isPast()) {
                throw ValidationException::withMessages(['meeting_room_id' => 'Only an upcoming or current confirmed lesson can receive a room.']);
            }
            $providerId ??= $locked->student_id ? Student::query()->whereKey($locked->student_id)->value('preferred_meeting_provider_id') : null;
            $providerId ??= MeetingProvider::query()->where('active', true)->where('is_default', true)->value('id');
            $rooms = MeetingRoom::query()->with('provider')->where('active', true)->whereHas('provider', fn ($query) => $query->where('active', true))
                ->when($roomId, fn ($query) => $query->whereKey($roomId))
                ->when(! $roomId && $providerId, fn ($query) => $query->where('meeting_provider_id', $providerId))->orderBy('id')->lockForUpdate()->get();
            foreach ($rooms as $room) {
                $collision = Booking::query()->where('meeting_room_id', $room->id)->where('id', '!=', $locked->id)
                    ->whereIn('status', ['confirmed', 'held', 'pending'])->where('start_at_utc', '<', $locked->end_at_utc)->where('end_at_utc', '>', $locked->start_at_utc)->lockForUpdate()->first(['id']) !== null;
                if ($collision) {
                    continue;
                }
                $old = $locked->only(['meeting_room_id', 'meeting_provider_snapshot']);
                $locked->update(['meeting_room_id' => $room->id, 'meeting_url_snapshot' => $room->url, 'meeting_provider_snapshot' => $room->provider->name, 'meeting_assigned_at' => now('UTC'), 'meeting_assigned_by' => $actorId]);
                app(AuditLogService::class)->log('meeting_room_assigned', Booking::class, $locked->id, $old, ['meeting_room_id' => $room->id, 'meeting_provider' => $room->provider->name], $actorId);

                return $locked;
            }
            if ($roomId) {
                throw ValidationException::withMessages(['meeting_room_id' => 'This room is disabled or already assigned to an overlapping lesson. Choose another room.']);
            }
            if ($locked->meeting_room_id !== null) {
                $locked->update(['meeting_room_id' => null, 'meeting_url_snapshot' => null, 'meeting_provider_snapshot' => null, 'meeting_assigned_at' => null, 'meeting_assigned_by' => null]);
                app(AuditLogService::class)->log('meeting_room_unassigned', Booking::class, $locked->id, ['meeting_room_id' => $booking->meeting_room_id], ['reason' => 'No eligible room after time change'], $actorId);
            }

            return $locked;
        }, 5);
    }

    public function studentUrl(Booking $booking): ?string
    {
        $lead = max(0, min(1440, (int) Setting::get('meeting.student_reveal_minutes', 15)));

        return $this->eligibleUrl($booking, $lead);
    }

    public function notificationUrl(Booking $booking, int $leadMinutes): ?string
    {
        return $this->eligibleUrl($booking, max(0, min(10080, $leadMinutes)));
    }

    private function eligibleUrl(Booking $booking, int $leadMinutes): ?string
    {
        if ($booking->status !== 'confirmed' || ! $booking->meeting_room_id || ! $booking->meeting_url_snapshot || $booking->end_at_utc->lte(now('UTC')) || $booking->start_at_utc->gt(now('UTC')->addMinutes($leadMinutes))) {
            return null;
        }
        if ($booking->student_id && ! Student::verified()->whereNull('suspended_at')->whereKey($booking->student_id)->exists()) {
            return null;
        }

        return Str::isUrl($booking->meeting_url_snapshot, ['https']) ? $booking->meeting_url_snapshot : null;
    }
}
