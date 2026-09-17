<?php

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Booking\Models\Booking;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;

class AdminNotificationService
{
    /**
     * Dispatch an internal admin notification with deduplication.
     */
    public function send(
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        array $data = [],
        string $level = 'info'
    ): AdminNotification {
        // Anti-spam deduplication: check if identical notification occurred in last 15 minutes
        $recentDuplicate = AdminNotification::query()
            ->where('type', $type)
            ->where('title', $title)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->first();

        if ($recentDuplicate) {
            $recentDuplicate->update([
                'message' => $message,
                'link' => $link ?? $recentDuplicate->link,
                'data' => array_merge($recentDuplicate->data ?? [], $data, ['repeat_count' => (($recentDuplicate->data['repeat_count'] ?? 1) + 1)]),
                'read_at' => null, // Bring back to unread if repeated
            ]);

            return $recentDuplicate;
        }

        return AdminNotification::create([
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'data' => $data,
        ]);
    }

    public function notifyBookingCreated(Booking $booking): AdminNotification
    {
        $contactName = $booking->contact->name ?? 'Student';
        $businessTz = $booking->business_timezone ?: app(TimezoneService::class)->getBusinessTimezone();
        $dateFormatted = CarbonImmutable::instance($booking->start_at_utc)
            ->setTimezone($businessTz)
            ->format('M j, Y \a\t g:i A');
        $tzLabel = $businessTz === 'Africa/Cairo' ? 'Cairo' : $businessTz;

        $existing = AdminNotification::query()
            ->where('type', 'booking_created')
            ->where('title', "New Booking #{$booking->id}")
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->send(
            type: 'booking_created',
            title: "New Booking #{$booking->id}",
            message: "{$contactName} booked a private session for {$dateFormatted} ({$tzLabel}).",
            link: route('admin.bookings.show', $booking->id),
            data: [
                'booking_id' => $booking->id,
                'student_name' => $contactName,
                'email' => $booking->contact->email ?? null,
                'session_type' => $booking->sessionType->name ?? null,
            ],
            level: 'success'
        );
    }

    public function notifyBookingCancelled(Booking $booking, string $reason = ''): AdminNotification
    {
        $contactName = $booking->contact->name ?? 'Student';
        $reasonText = $reason ? " Reason: {$reason}" : '';

        return $this->send(
            type: 'booking_cancelled',
            title: "Booking Cancelled #{$booking->id}",
            message: "Session with {$contactName} has been cancelled.{$reasonText}",
            link: route('admin.bookings.show', $booking->id),
            data: [
                'booking_id' => $booking->id,
                'student_name' => $contactName,
                'reason' => $reason,
            ],
            level: 'warning'
        );
    }

    public function notifyResourceRequested(ResourceRequest $request): AdminNotification
    {
        $contactName = $request->contact->name ?? 'Visitor';
        $contactEmail = $request->contact->email ?? 'unknown email';
        $resourceTitle = $request->resource->title ?? 'Learning Resource';

        return $this->send(
            type: 'resource_requested',
            title: 'New Resource Lead',
            message: "{$contactName} ({$contactEmail}) requested access to \"{$resourceTitle}\".",
            link: route('admin.leads'),
            data: [
                'request_id' => $request->id,
                'resource_id' => $request->resource_id,
                'contact_id' => $request->contact_id,
                'email' => $contactEmail,
            ],
            level: 'info'
        );
    }

    public function notifyBackupFailed(string $error): AdminNotification
    {
        return $this->send(
            type: 'backup_failure',
            title: 'Backup Failure Alert',
            message: "Automated backup failed: {$error}",
            link: route('admin.backups.index'),
            data: ['error' => $error],
            level: 'danger'
        );
    }

    public function notifyJobFailed(string $connection, string $queue, string $jobName, string $exception): AdminNotification
    {
        return $this->send(
            type: 'system_warning',
            title: "Job Failed: {$jobName}",
            message: "Queued background job on [{$connection}:{$queue}] failed: {$exception}",
            link: route('admin.health'),
            data: [
                'connection' => $connection,
                'queue' => $queue,
                'job' => $jobName,
                'error' => $exception,
            ],
            level: 'danger'
        );
    }

    public function notifySystemWarning(string $title, string $message, array $data = []): AdminNotification
    {
        return $this->send(
            type: 'system_warning',
            title: $title,
            message: $message,
            link: route('admin.health'),
            data: $data,
            level: 'warning'
        );
    }
}
