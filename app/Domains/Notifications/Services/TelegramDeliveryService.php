<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Jobs\DeliverTelegramMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TelegramDeliveryService
{
    /**
     * @param  list<string>  $parts
     * @param  list<list<array{type: string, offset: int, length: int, url?: string}>>  $entities
     */
    public function direct(TelegramDestination $destination, array $parts, string $identity, string $trigger = 'test', array $entities = [], array $options = []): TelegramDelivery
    {
        $delivery = TelegramDelivery::firstOrCreate(['dedupe_key' => hash('sha256', $trigger.':'.$destination->id.':'.$identity)], ['telegram_bot_id' => $destination->telegram_bot_id, 'telegram_destination_id' => $destination->id, 'trigger' => $trigger, 'payload' => ['parts' => $parts, 'entities' => $entities, 'options' => $options], 'due_at' => now('UTC')]);
        $this->deliver($delivery->id, true);

        return $delivery->refresh();
    }

    public function deliver(int $id, bool $explicit = false, ?string $expectedDedupeKey = null): void
    {
        $delivery = TelegramDelivery::with(['bot', 'destination', 'rule'])->find($id);
        if (! $delivery || ($expectedDedupeKey !== null && ! hash_equals($delivery->dedupe_key, $expectedDedupeKey))
            || $delivery->status !== 'pending' || $delivery->due_at->isFuture()) {
            return;
        }
        if (! $delivery->bot->enabled || ! $delivery->destination->enabled || ($delivery->rule && (! $delivery->rule->enabled || ! filter_var(Setting::get('telegram.automation_enabled', false), FILTER_VALIDATE_BOOLEAN)))) {
            $delivery->update(['status' => 'cancelled', 'failure_code' => 'disabled']);

            return;
        }
        $access = $delivery->payload['options'] ?? [];
        if ((isset($access['expires_at']) && $access['expires_at'] < now('UTC')->timestamp)
            || (isset($access['navigation_key']) && Cache::get($access['navigation_key']) !== $access['navigation_version'])) {
            $delivery->update(['status' => 'cancelled', 'failure_code' => 'report_expired_or_superseded']);

            return;
        }
        if (isset($access['command']) && (! $delivery->bot->commands_enabled
            || ! filter_var(Setting::get('telegram.commands_enabled', false), FILTER_VALIDATE_BOOLEAN)
            || ! app(TelegramCommandCatalog::class)->allows($delivery->bot, $access['command'])
            || ! in_array((string) ($access['user'] ?? ''), array_map('strval', $delivery->destination->allowed_user_ids ?? []), true)
            || (string) ($access['chat_id'] ?? '') !== (string) $delivery->destination->chat_id
            || (! empty($access['personal']) && $delivery->destination->detail_level !== 'personal'))) {
            $delivery->update(['status' => 'cancelled', 'failure_code' => 'command_access_revoked']);

            return;
        }
        if ($delivery->rule && isset($delivery->payload['source']) && ($delivery->payload['source']['_detail_level'] ?? null) !== $delivery->destination->detail_level) {
            $source = $delivery->payload['source'];
            $source['_detail_level'] = $delivery->destination->detail_level;
            if (in_array($delivery->trigger, ['morning_briefing', 'daily_wrapup', 'weekly_summary'], true)) {
                $source['digest'] = app(TelegramReadService::class)->digest($delivery->trigger, $delivery->rule->sections ?? [], $delivery->destination->detail_level === 'personal');
            }
            $parts = app(TelegramTemplateService::class)->deliveryParts($delivery->trigger, $delivery->rule->template, $source, $delivery->destination->detail_level === 'personal');
            if ($delivery->next_part > 0) {
                $delivery->update(['status' => 'cancelled', 'failure_code' => 'destination_privacy_changed']);

                return;
            }
            $delivery->update(['payload' => array_merge($parts, ['source' => $source])]);
        }
        if ($delivery->rule && $delivery->trigger === 'low_credits') {
            $source = $delivery->payload['source'] ?? [];
            $package = StudentPackage::query()->with(['student.packages.entitlements.type', 'student.packages.ledgerEntries'])->find($source['purchase_id'] ?? null);
            $entitlements = app(EntitlementService::class);
            if (! $package || ! $entitlements->eligible($package) || ! $package->student
                || ! $entitlements->allCreditsLow($package->student->packages, $delivery->rule->threshold)) {
                $delivery->update(['status' => 'cancelled', 'failure_code' => 'source_no_longer_eligible']);

                return;
            }
            $source['remaining_credits'] = app(TelegramReportFormatter::class)->credits($entitlements->forPackages($package->student->packages));
            $source['_detail_level'] = $delivery->destination->detail_level;
            $parts = app(TelegramTemplateService::class)->deliveryParts($delivery->trigger, $delivery->rule->template, $source, $delivery->destination->detail_level === 'personal');
            $delivery->update(['payload' => array_merge($parts, ['source' => $source])]);
        }
        if ($delivery->rule && in_array($delivery->trigger, TelegramFollowUpService::TRIGGERS, true)) {
            $source = app(TelegramFollowUpService::class)->currentFacts($delivery->rule, $delivery->payload['source'] ?? []);
            if ($source === null) {
                $delivery->update(['status' => 'cancelled', 'failure_code' => 'source_no_longer_eligible']);

                return;
            }
            $source['_detail_level'] = $delivery->destination->detail_level;
            $templates = app(TelegramTemplateService::class);
            $delivery->update(['payload' => array_merge($templates->deliveryParts($delivery->trigger, $delivery->rule->template, $source, $delivery->destination->detail_level === 'personal'), ['source' => $source])]);
        }
        if (in_array($delivery->trigger, ['session_reminder', 'missing_form'], true)) {
            $source = $delivery->payload['source'] ?? [];
            $booking = Booking::find($source['booking_id'] ?? null);
            $invalid = ! $booking || $booking->status !== 'confirmed' || ! $booking->start_at_utc->isFuture() || $booking->start_at_utc->timestamp !== ($source['_booking_start'] ?? null);
            if (! $invalid && $delivery->trigger === 'missing_form') {
                $invalid = FormSubmission::where('student_id', $booking->student_id)->where('form_version_id', $source['_form_version_id'] ?? null)->where('status', 'submitted')->exists();
            }
            if ($invalid) {
                $delivery->update(['status' => 'cancelled', 'failure_code' => 'source_no_longer_eligible']);

                return;
            }
        }
        if ($delivery->rule && isset($delivery->payload['source']['booking_id'])) {
            $source = $delivery->payload['source'];
            $booking = Booking::find($source['booking_id']);
            $source['meeting_url'] = $booking ? (app(MeetingLinkService::class)->notificationUrl($booking, (int) $delivery->rule->minutes) ?? 'Unavailable') : 'Unavailable';
            $source['meeting_provider'] = $booking->meeting_provider_snapshot ?? 'Unassigned';
            $templates = app(TelegramTemplateService::class);
            $parts = $templates->deliveryParts($delivery->trigger, $delivery->rule->template, $source, $delivery->destination->detail_level === 'personal');
            $delivery->update(['payload' => array_merge($parts, ['source' => $source])]);
        }
        $lock = Cache::lock('telegram-send-bot:'.$delivery->telegram_bot_id, 30);
        if (! $lock->get()) {
            if (config('queue.default') !== 'sync') {
                DeliverTelegramMessage::dispatch($delivery->id)->delay(now('UTC')->addSeconds(5));
            }

            return;
        }
        try {
            $chatKey = 'telegram-chat:'.$delivery->telegram_bot_id.':'.hash('sha256', $delivery->destination->chat_id);
            if (RateLimiter::tooManyAttempts($chatKey, 1) || RateLimiter::tooManyAttempts('telegram-bot:'.$delivery->telegram_bot_id, 20) || ($delivery->destination->type !== 'private' && RateLimiter::tooManyAttempts($chatKey.':minute', 20))) {
                $this->defer($delivery, 5);

                return;
            }
            if (TelegramDelivery::whereKey($id)->where('dedupe_key', $delivery->dedupe_key)->where('status', 'pending')->where('due_at', '<=', now('UTC'))->update(['status' => 'sending', 'attempted_at' => now('UTC'), 'attempts' => DB::raw('attempts + 1')]) !== 1) {
                return;
            }
            $delivery->refresh();
            $parts = $delivery->payload['parts'] ?? [];
            foreach ($parts as $index => $part) {
                if ($index < $delivery->next_part) {
                    continue;
                }
                RateLimiter::hit($chatKey, 1);
                RateLimiter::hit($chatKey.':minute', 60);
                RateLimiter::hit('telegram-bot:'.$delivery->telegram_bot_id, 60);
                try {
                    $message = ['chat_id' => $delivery->destination->chat_id, 'text' => $part, 'link_preview_options' => ['is_disabled' => true]];
                    if (! empty($delivery->payload['entities'][$index])) {
                        $message['entities'] = $delivery->payload['entities'][$index];
                    }
                    $options = $delivery->payload['options'] ?? [];
                    if ($delivery->rule?->priority === 'low') {
                        $options['silent'] = true;
                    }
                    $method = 'sendMessage';
                    if (count($parts) === 1 && isset($options['edit_message_id'])) {
                        $method = 'editMessageText';
                        $message['message_id'] = $options['edit_message_id'];
                    }
                    if ($index === count($parts) - 1 && ! empty($options['reply_markup'])) {
                        $message['reply_markup'] = $options['reply_markup'];
                    }
                    if ($method === 'sendMessage' && ! empty($options['silent'])) {
                        $message['disable_notification'] = true;
                    }
                    $response = Http::connectTimeout(2)->timeout(5)->post('https://api.telegram.org/bot'.$delivery->bot->token.'/'.$method, $message);
                } catch (\Throwable) {
                    $delivery->update(['status' => 'uncertain', 'failure_code' => 'network_outcome_unknown']);
                    $delivery->bot->update(['last_failure_at' => now('UTC'), 'failure_code' => 'network_outcome_unknown']);

                    return;
                }
                if ($response->json('ok') !== true) {
                    if ($method === 'editMessageText' && $response->json('error_code') === 400 && str_contains((string) $response->json('description', ''), 'message is not modified')) {
                        $delivery->update(['status' => 'sent', 'failure_code' => null]);

                        return;
                    }
                    $code = (int) ($response->json('error_code') ?? $response->status());
                    $retry = ($code === 429 || $code >= 500) && $delivery->attempts < 4;
                    $safe = 'telegram_'.$code;
                    $delivery->update(['status' => $retry ? 'pending' : 'failed', 'failure_code' => $safe]);
                    $delivery->bot->update(['last_failure_at' => now('UTC'), 'failure_code' => $safe]);
                    if ($retry) {
                        $this->defer($delivery, min(3600, max(10, (int) $response->json('parameters.retry_after', 0), 30 * (2 ** $delivery->attempts))));
                    }

                    return;
                }
                $ids = $delivery->message_ids ?? [];
                $ids[] = (string) $response->json('result.message_id');
                $delivery->update(['message_ids' => $ids, 'next_part' => $index + 1]);
                if ($index < count($parts) - 1) {
                    $delivery->update(['status' => 'pending']);
                    $this->defer($delivery, 3);

                    return;
                }
            }
            $source = $delivery->payload['source'] ?? [];
            if (isset($source['_legacy_key'], $source['booking_id'])) {
                DB::table('booking_events')->insertOrIgnore(['booking_id' => $source['booking_id'], 'event_type' => 'telegram_reminder_sent', 'idempotency_key' => $source['_legacy_key'], 'performed_by' => 'system', 'created_at' => now()]);
            }
            $delivery->update(['status' => 'sent', 'failure_code' => null]);
            $delivery->bot->update(['last_success_at' => now('UTC'), 'failure_code' => null]);
        } finally {
            $lock->release();
        }
    }

    private function defer(TelegramDelivery $delivery, int $seconds): void
    {
        $due = now('UTC')->addSeconds($seconds);
        $delivery->update(['due_at' => $due]);
        if (config('queue.default') !== 'sync') {
            DeliverTelegramMessage::dispatch($delivery->id)->delay($due);
        }
    }

    public function test(TelegramDestination $destination): TelegramDelivery
    {
        return $this->direct($destination, ['TEST — Telegram connectivity check. No student or customer data. '.now(app(TimezoneService::class)->getBusinessTimezone())->format('Y-m-d H:i').' Business time.'], (string) Str::uuid());
    }
}
