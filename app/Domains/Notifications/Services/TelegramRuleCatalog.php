<?php

namespace App\Domains\Notifications\Services;

class TelegramRuleCatalog
{
    /** @return array<string, array{label:string,group:string,modes:list<string>,fields:list<string>,template:string,defaults:array{minutes:int,threshold:int}}> */
    public function all(): array
    {
        $booking = ['booking_id', 'student_name', 'email', 'phone', 'session_title', 'tutor_time', 'student_time', 'booking_context', 'remaining_credits', 'session_number', 'total_sessions', 'admin_url', 'meeting_url'];
        $package = ['student_name', 'email', 'phone', 'package_name', 'remaining_credits', 'expiry_date', 'admin_url'];
        $definitions = [
            'booking_created' => ['Booking created', 'Bookings', $booking], 'booking_rescheduled' => ['Booking rescheduled', 'Bookings', $booking], 'booking_cancelled' => ['Booking cancelled', 'Bookings', $booking],
            'low_credits' => ['Low session credits', 'Students', $package], 'package_expiring' => ['Unused package approaching expiry', 'Students', $package],
            'form_submitted' => ['Form submitted', 'Forms', ['student_name', 'email', 'phone', 'form_title', 'admin_url']], 'missing_form' => ['Required form missing before lesson', 'Forms', array_merge($booking, ['form_title'])],
            'session_reminder' => ['Lesson countdown', 'Bookings', $booking], 'resource_lead' => ['Resource access requested', 'Resources', ['student_name', 'email', 'phone', 'resource_title', 'country', 'source', 'admin_url']],
            'resource_downloaded' => ['Resource downloaded', 'Resources', ['resource_title', 'country', 'source', 'admin_url']], 'hold_abandoned' => ['Contact left an expired booking hold', 'Bookings', ['student_name', 'email', 'phone', 'tutor_time', 'admin_url']],
            'traffic_spike' => ['Traffic spike', 'Analytics', ['unique_visitors', 'window_minutes', 'source']], 'scheduler_stale' => ['Scheduler heartbeat stale', 'System', ['last_heartbeat', 'threshold_minutes']], 'queue_stale' => ['Queue worker heartbeat stale', 'System', ['last_heartbeat', 'threshold_minutes']],
            'backup_success' => ['Backup completed', 'System', ['file_name', 'file_size', 'local_status', 'offsite_status']], 'backup_failure' => ['Backup failed', 'System', ['failure_code', 'local_status', 'offsite_status']],
            'maintenance_enabled' => ['Maintenance enabled', 'System', ['enabled_at', 'duration_minutes']], 'maintenance_duration' => ['Maintenance lasting too long', 'System', ['enabled_at', 'duration_minutes']],
            'business_digest' => ['Daily business digest', 'Digests', ['date', 'digest']], 'analytics_digest' => ['Daily analytics digest', 'Digests', ['date', 'digest']],
        ];
        $result = [];
        foreach ($definitions as $key => [$label,$group,$fields]) {
            $scheduled = in_array($key, ['business_digest', 'analytics_digest'], true);
            $template = $label."\n".implode("\n", array_map(fn (string $field): string => ucwords(str_replace('_', ' ', $field)).': {'.$field.'}', $fields));
            $result[$key] = ['label' => $label, 'group' => $group, 'fields' => $fields, 'modes' => $scheduled ? ['scheduled', 'on_demand'] : ['instant', 'delayed'], 'template' => $template, 'defaults' => ['minutes' => match ($key) {
                'missing_form' => 720, 'scheduler_stale', 'queue_stale' => 15, 'maintenance_duration' => 120, default => 60
            }, 'threshold' => match ($key) {
                'package_expiring' => 14, 'traffic_spike' => 50, default => 1
            }]];
        }

        return $result;
    }

    /** @return array{label:string,group:string,modes:list<string>,fields:list<string>,template:string,defaults:array{minutes:int,threshold:int}} */
    public function get(string $trigger): array
    {
        return $this->all()[$trigger] ?? throw new \InvalidArgumentException('Unknown alert type.');
    }
}
