<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use Carbon\CarbonImmutable;

class IcsGenerator
{
    public function __construct(private MeetingLinkService $meetingLinks) {}

    /**
     * Generate RFC 5545 compliant .ics string for a confirmed booking.
     */
    public function generate(Booking $booking): string
    {
        $dtStamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');
        $dtStart = CarbonImmutable::instance($booking->start_at_utc)->setTimezone('UTC')->format('Ymd\THis\Z');
        $dtEnd = CarbonImmutable::instance($booking->end_at_utc)->setTimezone('UTC')->format('Ymd\THis\Z');

        $sessionTitle = $this->escape(__($booking->sessionType->title ?? config('business.site_name').' Session'));
        $confirmationUrl = localized_url('booking.confirmation', $booking->confirmation_token);

        $meetingUrl = $this->meetingLinks->studentUrl($booking);

        $descriptionText = config('business.site_name').' '.__('Session')."\n"
            .__('Booking Reference').": {$booking->id}\n"
            .__('Student Time').": {$booking->customer_local_date_at_booking->format('Y-m-d')} {$booking->customer_local_start_time_at_booking} ({$booking->customer_timezone})\n"
            .__('Tutor Time').": {$booking->business_local_date_at_booking->format('Y-m-d')} {$booking->business_local_start_time_at_booking} ({$booking->business_timezone})\n"
            .($meetingUrl ? __('Video Classroom').": {$meetingUrl}\n" : '')
            .__('Booking details').": {$confirmationUrl}";

        $description = $this->escape($descriptionText);
        $uid = "booking-{$booking->id}@".parse_url(config('app.url'), PHP_URL_HOST);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.str_replace(['/', ':'], '-', config('business.site_name')).'//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "DTSTART:{$dtStart}",
            "DTEND:{$dtEnd}",
            "SUMMARY:{$sessionTitle}",
            "DESCRIPTION:{$description}",
            ...($meetingUrl ? ["LOCATION:{$meetingUrl}"] : []),
            "URL:{$confirmationUrl}",
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->foldLine(...), $lines))."\r\n";
    }

    protected function escape(string $text): string
    {
        return str_replace(
            ['\\', "\r\n", "\r", "\n", ';', ','],
            ['\\\\', '\n', '\n', '\n', '\;', '\,'],
            $text
        );
    }

    private function foldLine(string $line): string
    {
        $folded = '';
        while (strlen($line) > 75) {
            $part = mb_strcut($line, 0, 75, 'UTF-8');
            $folded .= $part."\r\n";
            $line = ' '.substr($line, strlen($part));
        }

        return $folded.$line;
    }
}
