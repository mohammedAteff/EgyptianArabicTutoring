<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Jobs\DeliverTelegramMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TelegramDeliveryService
{
    /** @param list<string> $parts */
    public function direct(TelegramDestination $destination, array $parts, string $identity, string $trigger = 'test'): TelegramDelivery
    {
        $delivery = TelegramDelivery::firstOrCreate(['dedupe_key' => hash('sha256', $trigger.':'.$destination->id.':'.$identity)], ['telegram_bot_id' => $destination->telegram_bot_id, 'telegram_destination_id' => $destination->id, 'trigger' => $trigger, 'payload' => ['parts' => $parts], 'due_at' => now('UTC')]);
        $this->deliver($delivery->id, true);

        return $delivery->refresh();
    }

    public function deliver(int $id, bool $explicit = false): void
    {
        $delivery = TelegramDelivery::with(['bot', 'destination', 'rule'])->find($id);
        if (! $delivery || $delivery->status !== 'pending' || $delivery->due_at->isFuture()) {
            return;
        }
        if (! $delivery->bot->enabled || ! $delivery->destination->enabled || ($delivery->rule && (! $delivery->rule->enabled || ! filter_var(Setting::get('telegram.automation_enabled', false), FILTER_VALIDATE_BOOLEAN)))) {
            $delivery->update(['status' => 'cancelled', 'failure_code' => 'disabled']);

            return;
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
        $lock = Cache::lock('telegram-send-bot:'.$delivery->telegram_bot_id, 30);
        if (! $lock->get()) {
            $this->defer($delivery, 5);

            return;
        }
        try {
            $chatKey = 'telegram-chat:'.$delivery->telegram_bot_id.':'.hash('sha256', $delivery->destination->chat_id);
            if (RateLimiter::tooManyAttempts($chatKey, 1) || RateLimiter::tooManyAttempts('telegram-bot:'.$delivery->telegram_bot_id, 20) || ($delivery->destination->type !== 'private' && RateLimiter::tooManyAttempts($chatKey.':minute', 20))) {
                $this->defer($delivery, 5);

                return;
            }
            if (TelegramDelivery::whereKey($id)->where('status', 'pending')->where('due_at', '<=', now('UTC'))->update(['status' => 'sending', 'attempted_at' => now('UTC'), 'attempts' => DB::raw('attempts + 1')]) !== 1) {
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
                    $response = Http::connectTimeout(2)->timeout(5)->post('https://api.telegram.org/bot'.$delivery->bot->token.'/sendMessage', ['chat_id' => $delivery->destination->chat_id, 'text' => $part, 'link_preview_options' => ['is_disabled' => true]]);
                } catch (\Throwable) {
                    $delivery->update(['status' => 'uncertain', 'failure_code' => 'network_outcome_unknown']);
                    $delivery->bot->update(['last_failure_at' => now('UTC'), 'failure_code' => 'network_outcome_unknown']);

                    return;
                }
                if ($response->json('ok') !== true) {
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
        return $this->direct($destination, ['TEST — Telegram connectivity check. No student or customer data. '.now('Africa/Cairo')->format('Y-m-d H:i').' Cairo time.'], (string) Str::uuid());
    }
}
