<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Analytics\Models\Visitor;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Students\Models\SessionLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramBusinessEvents
{
    public function register(): void
    {
        BookingEvent::created(function (BookingEvent $event): void {
            $trigger = match ($event->event_type) {
                'created' => 'booking_created','rescheduled' => 'booking_rescheduled','cancelled' => 'booking_cancelled', default => null
            };
            if ($trigger) {
                $this->after(function () use ($event, $trigger): void {
                    $booking = $event->booking()->first();
                    if ($booking) {
                        app(TelegramAutomationService::class)->emit($trigger, 'event:'.$event->id, app(TelegramReadService::class)->booking($booking));
                    }
                });
            }
        });
        SessionLedgerEntry::created(function (SessionLedgerEntry $entry): void {
            $this->after(function () use ($entry): void {
                $package = $entry->package;
                if ($package) {
                    app(TelegramReadService::class)->scanPackage($package);
                }
            });
        });
        FormSubmission::saved(function (FormSubmission $submission): void {
            if ($submission->status === 'submitted' && ($submission->wasChanged('submission_revision') || $submission->wasChanged('status') || $submission->wasRecentlyCreated)) {
                $this->after(function () use ($submission): void {
                    $submission->loadMissing(['student', 'version.form']);
                    app(TelegramAutomationService::class)->emit('form_submitted', 'submission:'.$submission->id.':'.$submission->submission_revision, ['student_name' => $submission->student?->name, 'email' => $submission->student?->email, 'phone' => $submission->student?->phone, 'form_title' => $submission->version?->form?->title, 'admin_url' => route('admin.forms.submissions', $submission->version->form_id)]);
                });
            }
        });
        ResourceRequest::created(function (ResourceRequest $request): void {
            $this->after(function () use ($request): void {
                $request->loadMissing(['contact', 'resource']);
                $country = Visitor::where('visitor_token', $request->visitor_token)->value('detected_country_code');
                app(TelegramAutomationService::class)->emit('resource_lead', 'request:'.$request->id, ['student_name' => $request->contact->name ?? 'Unknown', 'email' => $request->contact?->email, 'phone' => $request->contact?->phone, 'resource_title' => $request->resource?->title, 'country' => is_string($country) ? $country : 'ZZ', 'source' => $request->source ?: 'Unknown', 'admin_url' => route('admin.leads')]);
            });
        });
        ResourceDownload::created(function (ResourceDownload $download): void {
            $this->after(function () use ($download): void {
                $country = Visitor::where('visitor_token', $download->visitor_token)->value('detected_country_code');
                app(TelegramAutomationService::class)->emit('resource_downloaded', 'download:'.$download->id, ['resource_title' => $download->resource?->title, 'country' => is_string($country) ? $country : 'ZZ', 'source' => $download->request?->source ?: 'Unknown', 'admin_url' => route('admin.leads')]);
            });
        });
        Setting::saved(function (Setting $setting): void {
            if ($setting->key === 'maintenance_mode' && ($setting->wasChanged('value') || $setting->wasRecentlyCreated)) {
                $enabled = filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
                $was = $setting->wasRecentlyCreated ? false : filter_var($setting->getRawOriginal('value'), FILTER_VALIDATE_BOOLEAN);
                if ($enabled !== $was) {
                    $this->after(function () use ($enabled): void {
                        $since = now('UTC')->toIso8601String();
                        Setting::set('telegram.maintenance_since', $enabled ? $since : '', 'telegram');
                        if ($enabled) {
                            app(TelegramAutomationService::class)->emit('maintenance_enabled', 'maintenance:'.$since, ['enabled_at' => $since, 'duration_minutes' => 0]);
                        }
                    });
                }
            }
        });
    }

    public function expiredHold(BookingHold $hold): void
    {
        $lead = $hold->lead_details;
        if (! is_array($lead) || ! filter_var($lead['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $this->after(fn () => app(TelegramAutomationService::class)->emit('hold_abandoned', 'hold:'.$hold->id, ['student_name' => $lead['name'] ?? 'Unknown', 'email' => $lead['email'], 'phone' => $lead['phone'] ?? null, 'tutor_time' => $hold->slot_start_utc->copy()->setTimezone((string) Setting::get('business_timezone', 'Africa/Cairo'))->format('Y-m-d H:i T'), 'admin_url' => route('admin.bookings.index')]));
    }

    private function after(callable $callback): void
    {
        DB::afterCommit(function () use ($callback): void {
            try {
                $callback();
            } catch (\Throwable $e) {
                Log::warning('Telegram post-commit event failed.', ['exception_type' => $e::class]);
            }
        });
    }
}
