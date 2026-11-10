<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Policies\StudentTeachingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StudentNotificationService
{
    public function localizedMessage(string $message): string
    {
        if (preg_match('/^Session #(\d+) · (.+)\. Check your session details for the current date and time\.$/u', $message, $parts)) {
            return __('Session #:reference · :status. Check your session details for the current date and time.', ['reference' => $parts[1], 'status' => __($parts[2])]);
        }

        if (preg_match('/^(.+?) · (.+) · Purchase(?: #(\d+))? · (\d+) available · Valid until: (.+)$/u', $message, $parts)) {
            return __(':type · :package · :purchase · :availability · Valid until: :expiry', [
                'type' => __($parts[1]), 'package' => __($parts[2]),
                'purchase' => $parts[3] !== '' ? __('Purchase #:reference', ['reference' => $parts[3]]) : __('Purchase'),
                'availability' => trans_choice(':count available', (int) $parts[4], ['count' => localized_number((int) $parts[4])]),
                'expiry' => $this->localizedDateLabel($parts[5]),
            ]);
        }

        $waitlistSuffix = '. Review current availability and confirm to book. This is not a reservation.';
        if (str_ends_with($message, $waitlistSuffix)) {
            $label = substr($message, 0, -strlen($waitlistSuffix));

            return $this->localizedDateLabel($label).__($waitlistSuffix);
        }

        return __($message);
    }

    private function localizedDateLabel(string $label): string
    {
        if (! in_array(app()->getLocale(), ['fr', 'de'], true)) {
            return $label;
        }

        if (preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun) (\d{1,2}) ([A-Z][a-z]{2}), (\d{1,2}):(\d{2}) (AM|PM)–(\d{1,2}):(\d{2}) (AM|PM) · (.+)$/u', $label, $parts)) {
            $startHour = (int) $parts[4] % 12 + ($parts[6] === 'PM' ? 12 : 0);
            $endHour = (int) $parts[7] % 12 + ($parts[9] === 'PM' ? 12 : 0);

            return __($parts[1]).' '.$parts[2].(app()->getLocale() === 'de' ? '.' : '').' '.__($parts[3]).', '.sprintf('%02d:%s–%02d:%s', $startHour, $parts[5], $endHour, $parts[8]).' · '.$parts[10];
        }

        try {
            if (preg_match('/^\d{1,2} [A-Z][a-z]{2} \d{4}, \d{1,2}:\d{2}:\d{2} (AM|PM) \S+$/', $label)) {
                return localized_date(CarbonImmutable::createFromFormat('!j M Y, g:i:s A T', $label), 'j M Y, g:i:s A T');
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $label)) {
                return localized_date(CarbonImmutable::createFromFormat('!Y-m-d', $label), 'j M Y');
            }
        } catch (\Throwable) {
            return $label;
        }

        return __($label);
    }

    /** @param list<array{key: string, type: string, title: string, message: string, link: string}> $events */
    public function synchronize(Student $student, array $events): void
    {
        DB::transaction(function () use ($student, $events): void {
            $locked = Student::query()->lockForUpdate()->find($student->id);
            if (! $locked || ! app(StudentTeachingPolicy::class)->usePortal($locked)) {
                return;
            }
            $now = now('UTC');
            $rows = array_map(fn (array $event): array => [
                'student_id' => $locked->id, 'deduplication_key' => hash('sha256', $event['key']),
                'type' => $event['type'], 'title' => $event['title'], 'message' => $event['message'],
                'link' => $event['link'], 'created_at' => $now, 'updated_at' => $now,
            ], $events);
            if ($rows !== []) {
                StudentNotification::query()->insertOrIgnore($rows);
            }
        }, 3);
    }
}
