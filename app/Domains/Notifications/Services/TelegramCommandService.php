<?php

namespace App\Domains\Notifications\Services;

use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramBot;
use App\Domains\Notifications\Models\TelegramDestination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;

class TelegramCommandService
{
    public function __construct(private TelegramNavigationService $navigation, private TelegramCommandCatalog $catalog, private TelegramReportFormatter $formatter, private TelegramDeliveryService $delivery) {}

    public function publishMenu(TelegramBot $bot): bool
    {
        if (! $bot->enabled || ! $bot->commands_enabled || ! filter_var(Setting::get('telegram.commands_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }
        $basic = [['command' => 'start', 'description' => 'Open the business dashboard'], ['command' => 'help', 'description' => 'See available reports and search help']];
        $requests = [['commands' => $basic, 'scope' => ['type' => 'default']]];
        foreach ($bot->destinations()->where('enabled', true)->whereIn('type', ['private', 'group'])->get() as $destination) {
            $commands = $basic;
            foreach ($this->catalog->all() as $command => $label) {
                if ($this->catalog->allows($bot, $command) && ($destination->detail_level === 'personal' || ! in_array($command, ['student', 'money', 'brief', 'wrap'], true))) {
                    $commands[] = ['command' => $command, 'description' => preg_replace('/^[^\p{L}\p{N}]+/u', '', $label)];
                }
            }
            foreach ($destination->allowed_user_ids ?? [] as $user) {
                $scope = $destination->type === 'private' ? ['type' => 'chat', 'chat_id' => $destination->chat_id]
                    : ['type' => 'chat_member', 'chat_id' => $destination->chat_id, 'user_id' => (int) $user];
                $requests[json_encode($scope)] = ['commands' => $commands, 'scope' => $scope];
            }
        }
        foreach ($requests as $payload) {
            try {
                if (Http::connectTimeout(2)->timeout(5)->post('https://api.telegram.org/bot'.$bot->token.'/setMyCommands', $payload)->json('ok') !== true) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    public function listen(int $seconds): void
    {
        $deadline = microtime(true) + max(0, min(45, $seconds));
        $pollDeadline = $deadline - min(7, $seconds / 4);
        $passes = max(1, (int) ceil($seconds / 2));
        for ($pass = 0; $pass < $passes; $pass++) {
            $this->poll($seconds > 0 ? $pollDeadline : null);
            if ($seconds === 0 || microtime(true) >= $pollDeadline - 2 || ! filter_var(Setting::get('telegram.commands_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
                break;
            }
            if ($pass + 1 < $passes) {
                Sleep::for(2)->seconds();
            }
        }
    }

    public function poll(?float $deadline = null): void
    {
        if (! filter_var(Setting::get('telegram.commands_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        foreach (TelegramBot::where('enabled', true)->where('commands_enabled', true)->get() as $bot) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            $lock = Cache::lock('telegram-poll:'.$bot->id, 60);
            if (! $lock->get()) {
                continue;
            }
            try {
                try {
                    $response = Http::connectTimeout(2)->timeout(5)->get('https://api.telegram.org/bot'.$bot->token.'/getUpdates', ['offset' => $bot->update_offset, 'limit' => 50, 'timeout' => 0, 'allowed_updates' => json_encode(['message', 'callback_query'])]);
                } catch (\Throwable) {
                    $bot->update(['last_failure_at' => now('UTC'), 'failure_code' => 'poll_network_failure']);

                    continue;
                }
                if ($response->json('ok') !== true) {
                    $bot->update(['last_failure_at' => now('UTC'), 'failure_code' => 'poll_rejected']);

                    continue;
                }
                $updates = $response->json('result', []);
                if (! is_array($updates)) {
                    continue;
                }
                foreach ($updates as $update) {
                    if ($deadline !== null && microtime(true) >= $deadline) {
                        break;
                    }
                    if (! is_array($update) || ! is_numeric($update['update_id'] ?? null)) {
                        continue;
                    }
                    $this->handle($bot, $update);
                    $bot->update(['update_offset' => max($bot->update_offset, (int) $update['update_id'] + 1)]);
                }
            } finally {
                $lock->release();
            }
        }
    }

    /** @param array<string,mixed> $update */
    public function handle(TelegramBot $bot, array $update): void
    {
        if (! $bot->enabled || ! $bot->commands_enabled || ! filter_var(Setting::get('telegram.commands_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        $callback = $update['callback_query'] ?? null;
        $message = $callback['message'] ?? $update['message'] ?? [];
        $chat = (string) ($message['chat']['id'] ?? '');
        $user = (string) ($callback['from']['id'] ?? $message['from']['id'] ?? '');
        $destination = TelegramDestination::where('telegram_bot_id', $bot->id)->where('chat_id', $chat)->where('enabled', true)->first();
        if (! $destination || $user === '' || ! in_array($user, array_map('strval', $destination->allowed_user_ids ?? []), true)) {
            return;
        }
        $key = 'telegram-command:'.$bot->id.':'.hash('sha256', $chat.':'.$user).($callback !== null ? ':navigation' : '');
        if (RateLimiter::tooManyAttempts($key, $callback !== null ? 60 : 20)) {
            if ($callback !== null) {
                $this->acknowledge($bot, (string) ($callback['id'] ?? ''), 'Please wait a moment before opening another report.');
            }

            return;
        } RateLimiter::hit($key, 60);
        if ($callback !== null) {
            $state = $this->navigation->callback($bot, $destination, $user, (string) ($callback['data'] ?? ''));
            $this->acknowledge($bot, (string) ($callback['id'] ?? ''), $state === null ? 'This menu expired or is unavailable. Send /start for a fresh menu.' : '');
            if ($state === null || ! is_numeric($message['message_id'] ?? null)) {
                return;
            }
            $navigationKey = 'telegram-view:'.$bot->id.':'.$destination->id.':'.$message['message_id'];
            $selected = Cache::lock($navigationKey.':lock', 5)->block(2, function () use ($navigationKey, $update): bool {
                if ((int) Cache::get($navigationKey, 0) >= (int) $update['update_id']) {
                    return false;
                }
                Cache::put($navigationKey, (int) $update['update_id'], now('UTC')->addMinutes(35));

                return true;
            });
            if (! $selected) {
                return;
            }
        } else {
            $text = $message['text'] ?? '';
            if (! is_string($text) || ! preg_match('/^\/([a-z_]{1,32})(?:@[A-Za-z0-9_]+)?(?:\s+(.{1,120}))?$/u', trim($text), $matches)) {
                return;
            }
            $command = $matches[1];
            if (! $this->catalog->allows($bot, $command)) {
                return;
            }
            $state = $this->navigation->state($command, trim($matches[2] ?? ''));
        }
        try {
            $page = $this->navigation->render($bot, $destination, $user, $state);
        } catch (ValidationException) {
            $page = ['text' => 'Use /stats 7d or /stats 30d. You can also use today, yesterday, or two dates (YYYY-MM-DD YYYY-MM-DD), up to 90 days ending today or earlier.', 'reply_markup' => []];
        } catch (\Throwable $exception) {
            Log::warning('Telegram report could not be prepared.', ['command' => $state['command'], 'exception_type' => $exception::class]);
            $page = ['text' => 'This report is temporarily unavailable. Try again or open the admin website.', 'reply_markup' => []];
        }
        $formatted = $this->formatter->format($page['text']);
        $parts = $this->formatter->split($formatted['text'], $formatted['entities']);
        $options = ['reply_markup' => $page['reply_markup'], 'command' => $state['command'], 'user' => $user, 'chat_id' => $destination->chat_id, 'personal' => $destination->detail_level === 'personal', 'expires_at' => now('UTC')->addMinutes(5)->timestamp];
        if ($callback !== null && count($parts['parts']) === 1) {
            $options['edit_message_id'] = (int) $message['message_id'];
        }
        if ($callback !== null) {
            $options['navigation_key'] = $navigationKey;
            $options['navigation_version'] = (int) $update['update_id'];
        }
        $this->delivery->direct($destination, $parts['parts'], 'update:'.(string) $update['update_id'], 'command_'.$state['command'], $parts['entities'], $options);
    }

    private function acknowledge(TelegramBot $bot, string $callbackId, string $text): void
    {
        if ($callbackId === '') {
            return;
        }
        try {
            Http::connectTimeout(2)->timeout(5)->post('https://api.telegram.org/bot'.$bot->token.'/answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
        } catch (\Throwable) {
            Log::warning('Telegram navigation acknowledgement failed.', ['bot_id' => $bot->id]);
        }
    }
}
