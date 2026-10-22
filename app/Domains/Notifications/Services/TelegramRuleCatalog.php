<?php

namespace App\Domains\Notifications\Services;

class TelegramRuleCatalog
{
    public const DIGESTS = ['business_digest', 'analytics_digest', 'morning_briefing', 'daily_wrapup', 'weekly_summary'];

    /** @return array<string, array{label:string,group:string,modes:list<string>,fields:list<string>,template:string,defaults:array{minutes:int,threshold:int}}> */
    public function all(): array
    {
        $booking = ['booking_id', 'student_name', 'email', 'phone', 'session_title', 'tutor_time', 'student_time', 'booking_context', 'remaining_credits', 'session_number', 'total_sessions', 'admin_url', 'meeting_url', 'meeting_provider'];
        $package = ['student_name', 'email', 'phone', 'package_name', 'offering_key', 'purchase_id', 'entitlement_code', 'entitlement_label', 'allocation_id', 'available_units', 'remaining_credits', 'expiry_date', 'admin_url'];
        $definitions = [
            'booking_created' => ['Booking created', 'Bookings', $booking], 'booking_rescheduled' => ['Booking rescheduled', 'Bookings', $booking], 'booking_cancelled' => ['Booking cancelled', 'Bookings', $booking],
            'low_credits' => ['Low session credits', 'Finance', $package], 'package_expiring' => ['Unused package approaching expiry', 'Finance', $package],
            'form_submitted' => ['Form submitted', 'Forms', ['student_name', 'email', 'phone', 'form_title', 'admin_url']], 'missing_form' => ['Required form missing before lesson', 'Forms', array_merge($booking, ['form_title'])],
            'session_reminder' => ['Lesson countdown', 'Bookings', $booking], 'resource_lead' => ['Resource access requested', 'Resources', ['student_name', 'email', 'phone', 'resource_title', 'country', 'source', 'admin_url']],
            'resource_downloaded' => ['Resource downloaded', 'Resources', ['resource_title', 'country', 'source', 'admin_url']], 'hold_abandoned' => ['Contact left an expired booking hold', 'Bookings', ['student_name', 'email', 'phone', 'tutor_time', 'admin_url']],
            'traffic_spike' => ['Traffic spike', 'Analytics', ['unique_visitors', 'window_minutes', 'source']], 'scheduler_stale' => ['Scheduler heartbeat stale', 'System', ['last_heartbeat', 'threshold_minutes']], 'queue_stale' => ['Queue worker heartbeat stale', 'System', ['last_heartbeat', 'threshold_minutes']],
            'backup_success' => ['Backup completed', 'System', ['file_name', 'file_size', 'local_status', 'offsite_status']], 'backup_failure' => ['Backup failed', 'System', ['failure_code', 'local_status', 'offsite_status']],
            'maintenance_enabled' => ['Maintenance enabled', 'System', ['enabled_at', 'duration_minutes']], 'maintenance_duration' => ['Maintenance lasting too long', 'System', ['enabled_at', 'duration_minutes']],
            'business_digest' => ['Daily business digest', 'Digests', ['date', 'digest']], 'analytics_digest' => ['Daily analytics digest', 'Digests', ['date', 'digest']],
            'morning_briefing' => ['Morning briefing', 'Digests', ['date', 'digest']], 'daily_wrapup' => ['Daily wrap-up', 'Digests', ['date', 'digest']],
            'weekly_summary' => ['Weekly business summary (Mondays)', 'Digests', ['date', 'digest']],
        ];
        foreach (TelegramActivityEvents::AUDIT_ALERTS as $key => [$label, $group]) {
            $definitions[$key] = [$label, $group, ['event', 'student_name', 'record_type', 'record_id', 'status', 'occurred_at', 'admin_url']];
        }
        foreach (['payment_recorded' => 'Payment recorded', 'payment_refunded' => 'Payment refunded', 'package_created' => 'Package purchased'] as $key => $label) {
            $definitions[$key] = [$label, 'Finance', ['student_name', 'record_id', 'amount', 'currency', 'admin_url']];
        }
        $definitions['student_verified'] = ['Student profile verified', 'Students', ['student_name', 'admin_url']];
        foreach (['waitlist_opening' => ['Preferred slot is available', 'Waitlist'], 'student_birthday' => ['Birthday reminder', 'Students'], 'system_warning' => ['System warning or failed background job', 'System']] as $key => [$label, $group]) {
            $definitions[$key] = [$label, $group, ['student_name', 'notification_id', 'birthday', 'details', 'admin_url']];
        }
        $definitions['homework_submitted'] = ['Homework submitted', 'Learning', ['student_name', 'record_id', 'admin_url']];
        $definitions['lesson_feedback'] = ['Student lesson feedback submitted', 'Students', ['student_name', 'booking_id', 'admin_url']];
        $definitions['installment_overdue'] = ['Installment payment overdue', 'Finance', ['student_name', 'record_id', 'amount', 'currency', 'due_date', 'admin_url']];
        $definitions['staff_task_overdue'] = ['Staff task overdue', 'Staff', ['student_name', 'record_id', 'due_date', 'admin_url']];
        $definitions['meeting_missing'] = ['Lesson approaching without meeting details', 'Bookings', $booking];
        $result = [];
        foreach ($definitions as $key => [$label,$group,$fields]) {
            $scheduled = in_array($key, self::DIGESTS, true);
            $templateFields = match ($key) {
                'waitlist_opening' => ['student_name', 'details', 'admin_url'],
                'system_warning' => ['notification_id', 'details', 'admin_url'],
                default => $fields,
            };
            $template = $label."\n".implode("\n", array_map(fn (string $field): string => ucwords(str_replace('_', ' ', $field)).': {'.$field.'}', $templateFields));
            $template = match ($key) {
                'booking_created', 'booking_rescheduled', 'booking_cancelled', 'session_reminder' => '📅 '.$label."\n\n{student_name}\n{session_title}\n\n🕒 {tutor_time}\nStudent: {student_time}\n\n{booking_context}\nCredits: {remaining_credits}\n\n{email}\n{phone}\n\n{meeting_url}\n{admin_url}",
                'missing_form' => "📝 Required form outstanding\n\n{student_name}\n{form_title}\n\nLesson: {tutor_time}\n\n{admin_url}",
                'low_credits' => "🔔 Low lesson credits\n\n{student_name}\n\nAvailable credits\n{remaining_credits}\n\n{admin_url}",
                'package_expiring' => "⏳ Credits approaching expiry\n\n{student_name}\n{package_name}\n\n{remaining_credits}\nExpires: {expiry_date}\n\n{admin_url}",
                'payment_recorded', 'payment_refunded', 'package_created' => '💳 '.$label."\n\n{student_name}\n{amount} {currency}\n\n{admin_url}",
                'installment_overdue' => "⚠️ Installment overdue\n\n{student_name}\n{amount} {currency}\nDue: {due_date}\n\n{admin_url}",
                'resource_lead', 'resource_downloaded' => '📚 '.$label."\n\n{resource_title}\n\nCountry: {country}\nSource: {source}\n\n{admin_url}",
                'homework_submitted' => $label."\n\n{student_name}\nHomework #{record_id}\n\n{admin_url}",
                'student_verified' => $label."\n\n{student_name}\n\n{admin_url}",
                'lesson_feedback' => $label."\n\n{student_name}\nLesson #{booking_id}\n\n{admin_url}",
                'staff_task_overdue' => $label."\n\nTask #{record_id}\nDue: {due_date}\n\n{admin_url}",
                'business_digest', 'analytics_digest', 'morning_briefing', 'daily_wrapup', 'weekly_summary' => $label." · {date}\n\n{digest}",
                default => $template,
            };
            $result[$key] = ['label' => $label, 'group' => $group, 'fields' => $fields, 'modes' => $scheduled ? ['scheduled', 'on_demand'] : ($key === 'meeting_missing' ? ['instant'] : ['instant', 'delayed']), 'template' => $template, 'defaults' => ['minutes' => match ($key) {
                'missing_form' => 720, 'scheduler_stale', 'queue_stale' => 15, 'maintenance_duration' => 120, default => 60
            }, 'threshold' => match ($key) {
                'package_expiring' => 14, 'traffic_spike' => 50, default => 1
            }]];
        }

        uasort($result, fn (array $first, array $second): int => strnatcasecmp($first['group'].':'.$first['label'], $second['group'].':'.$second['label']));

        return $result;
    }

    /** @return array{label:string,group:string,modes:list<string>,fields:list<string>,template:string,defaults:array{minutes:int,threshold:int}} */
    public function get(string $trigger): array
    {
        return $this->all()[$trigger] ?? throw new \InvalidArgumentException('Unknown alert type.');
    }

    public function isDefault(string $trigger, string $template): bool
    {
        $definition = $this->get($trigger);
        $fields = match ($trigger) {
            'waitlist_opening' => ['student_name', 'details', 'admin_url'],
            'system_warning' => ['notification_id', 'details', 'admin_url'],
            default => $definition['fields'],
        };
        $legacy = $definition['label']."\n".implode("\n", array_map(fn (string $field): string => ucwords(str_replace('_', ' ', $field)).': {'.$field.'}', $fields));

        $previous = match ($trigger) {
            'booking_created', 'booking_rescheduled', 'booking_cancelled', 'session_reminder' => '📅 '.$definition['label']."\n{student_name} · {session_title}\n🕒 {tutor_time}\nStudent time: {student_time}\n{booking_context}\nCredits: {remaining_credits}\nContact: {email} · {phone}\nMeeting: {meeting_url}\n{admin_url}",
            'missing_form' => "📝 Required form outstanding\n{student_name} · {form_title}\nLesson: {tutor_time}\n{admin_url}",
            'low_credits' => "🔔 Low lesson credits\n{student_name}\n{package_name}\n{remaining_credits}\n{admin_url}",
            'package_expiring' => "⏳ Unused credits approaching expiry\n{student_name}\n{package_name}\n{remaining_credits}\nExpires: {expiry_date}\n{admin_url}",
            'payment_recorded', 'payment_refunded', 'package_created' => '💳 '.$definition['label']."\n{student_name}\n{amount} {currency}\n{admin_url}",
            'installment_overdue' => "⚠️ Installment overdue\n{student_name}\n{amount} {currency} · due {due_date}\n{admin_url}",
            'resource_lead', 'resource_downloaded' => '📚 '.$definition['label']."\n{resource_title}\nCountry: {country} · Source: {source}\n{admin_url}",
            default => $legacy,
        };

        return in_array($template, [$definition['template'], $legacy, $previous], true);
    }
}
