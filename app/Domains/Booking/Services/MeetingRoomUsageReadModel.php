<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MeetingRoomUsageReadModel
{
    /** @return Collection<int, array<string, mixed>> */
    public function overview(string $dateFrom, string $dateTo): Collection
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $from = CarbonImmutable::parse($dateFrom, $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($dateTo, $timezone)->startOfDay()->addDay()->utc();
        $usage = Booking::query()->whereNotNull('meeting_room_id')->where('status', 'completed')->where('start_at_utc', '>=', $from)->where('start_at_utc', '<', $to)
            ->select('meeting_room_id')->selectRaw('COUNT(*) as usage_count')->groupBy('meeting_room_id')->pluck('usage_count', 'meeting_room_id');
        $upcoming = Booking::query()->whereNotNull('meeting_room_id')->whereIn('status', ['confirmed', 'held', 'pending'])->where('end_at_utc', '>', now('UTC'))->orderBy('start_at_utc')->get(['id', 'meeting_room_id', 'start_at_utc', 'end_at_utc'])->groupBy('meeting_room_id');

        return MeetingRoom::query()->with('provider:id,name,active')->orderBy('meeting_provider_id')->orderBy('name')->get()->map(fn (MeetingRoom $room): array => [
            'room' => $room, 'used' => (int) ($usage[$room->id] ?? 0), 'next' => $upcoming->get($room->id)?->first(),
            'busy' => $upcoming->get($room->id)?->contains(fn (Booking $booking): bool => $booking->start_at_utc->lessThanOrEqualTo(now('UTC'))) ?? false,
        ]);
    }
}
