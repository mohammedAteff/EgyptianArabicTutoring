<?php

namespace App\Domains\Notifications\Services;

use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramRule;
use App\Domains\Notifications\Models\TelegramRuleState;
use App\Jobs\DeliverTelegramMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TelegramAutomationService
{
    public function __construct(private TelegramTemplateService $templates) {}

    /** @param array<string, scalar|null> $payload */
    public function emit(string $trigger, string $identity, array $payload, ?string $cooldownEntity = null, ?int $ruleId = null): void
    {
        if (! Schema::hasTable('telegram_rules') || ! filter_var(Setting::get('telegram.automation_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        try {
            $rules = TelegramRule::query()->where('trigger', $trigger)->where('enabled', true)->whereHas('bot', fn ($q) => $q->where('enabled', true));
            if ($ruleId !== null) {
                $rules->whereKey($ruleId);
            }
            foreach ($rules->get() as $rule) {
                $this->enqueue($rule, $identity, $payload, $cooldownEntity);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram event could not be queued.', ['trigger' => $trigger, 'exception_type' => $e::class]);
        }
    }

    /** @param array<string, scalar|null> $payload */
    private function enqueue(TelegramRule $rule, string $identity, array $payload, ?string $entity): void
    {
        DB::transaction(function () use ($rule, $identity, $payload, $entity): void {
            $locked = TelegramRule::query()->lockForUpdate()->findOrFail($rule->id);
            foreach ($locked->conditions ?? [] as $field => $value) {
                if ($value !== null && $value !== '' && (string) ($payload[$field] ?? '') !== (string) $value) {
                    return;
                }
            }
            $state = null;
            if ($entity !== null) {
                $state = TelegramRuleState::firstOrCreate(['telegram_rule_id' => $rule->id, 'entity' => hash('sha256', $entity)]);
                if ($state->last_emitted_at && $state->last_emitted_at->gt(now('UTC')->subMinutes($rule->cooldown_minutes))) {
                    return;
                }
            }
            $due = CarbonImmutable::now('UTC')->addMinutes($rule->mode === 'delayed' ? $rule->minutes : 0);
            if ($rule->priority !== 'critical' && $rule->quiet_start && $rule->quiet_end) {
                $tz = (string) Setting::get('business_timezone', 'Africa/Cairo');
                $local = $due->setTimezone($tz);
                $time = $local->format('H:i');
                $quiet = $rule->quiet_start < $rule->quiet_end ? ($time >= $rule->quiet_start && $time < $rule->quiet_end) : ($time >= $rule->quiet_start || $time < $rule->quiet_end);
                if ($quiet) {
                    $end = $local->setTimeFromTimeString($rule->quiet_end);
                    if ($end->lte($local)) {
                        $end = $end->addDay();
                    } $due = $end->setTimezone('UTC');
                }
            }
            $created = false;
            foreach ($locked->destinations()->where('enabled', true)->get() as $destination) {
                if ($rule->legacy_reminder && isset($payload['booking_id'])) {
                    $legacyKey = 'reminder_'.$payload['booking_id'].'_'.$rule->minutes.'m_'.$destination->chat_id;
                    if (DB::table('booking_events')->where('idempotency_key', $legacyKey)->exists()) {
                        continue;
                    }
                    $payload['_legacy_key'] = $legacyKey;
                }
                $dedupe = hash('sha256', $rule->id.':'.$destination->id.':'.$identity);
                $text = $this->templates->render($rule->trigger, $rule->template, $payload, $destination->detail_level === 'personal');
                try {
                    $delivery = TelegramDelivery::create(['telegram_bot_id' => $rule->telegram_bot_id, 'telegram_destination_id' => $destination->id, 'telegram_rule_id' => $rule->id, 'dedupe_key' => $dedupe, 'trigger' => $rule->trigger, 'payload' => ['parts' => $this->templates->split($text), 'source' => $payload], 'due_at' => $due]);
                    $created = true;
                    DB::afterCommit(fn () => DeliverTelegramMessage::dispatch($delivery->id)->delay($due));
                } catch (UniqueConstraintViolationException) {
                }
            }
            if ($created && $state) {
                $state->update(['last_emitted_at' => now('UTC')]);
            }
        });
    }
}
