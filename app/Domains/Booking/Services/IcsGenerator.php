<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;

class IcsGenerator
{
    /**
     * Generate RFC 5545 compliant .ics string for a confirmed booking.
     */
    public function generate(Booking $booking): string
    {
        $dtStamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');
        $dtStart = CarbonImmutable::instance($booking->start_at_utc)->setTimezone('UTC')->format('Ymd\THis\Z');
        $dtEnd = CarbonImmutable::instance($booking->end_at_utc)->setTimezone('UTC')->format('Ymd\THis\Z');

        $sessionTitle = $this->escape($booking->sessionType?->title ?? config('business.site_name').' Session');
        $confirmationUrl = url("/booking/confirmation/{$booking->confirmation_token}");

        $meetingUrl = Setting::get('video_meeting_url', 'https://meet.google.com');

        $descriptionText = config('business.site_name').' Session\\n'
            ."Booking Reference: {$booking->confirmation_token}\\n"
            ."Student Time: {$booking->customer_local_date_at_booking?->format('Y-m-d')} {$booking->customer_local_start_time_at_booking} ({$booking->customer_timezone})\\n"
            ."Tutor Time: {$booking->business_local_date_at_booking?->format('Y-m-d')} {$booking->business_local_start_time_at_booking} ({$booking->business_timezone})\\n"
            ."Video Classroom: {$meetingUrl}\\n"
            ."Confirmation & Management: {$confirmationUrl}";

        $description = $this->escape($descriptionText);
        $uid = "booking-{$booking->confirmation_token}@".parse_url(config('app.url'), PHP_URL_HOST);

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
            "LOCATION:{$meetingUrl}",
            "URL:{$confirmationUrl}",
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    protected function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ','],
            ['\\\\', '\;', '\,'],
            $text
        );
    }
}
