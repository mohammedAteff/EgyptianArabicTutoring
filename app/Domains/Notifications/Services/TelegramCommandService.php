<?php

namespace App\Domains\Notifications\Services;

use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramBot;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentIdentityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class TelegramCommandService
{
    public function __construct(private TelegramReadService $read, private TelegramTemplateService $templates, private TelegramDeliveryService $delivery) {}

    public function poll(): void
    {
        if (! filter_var(Setting::get('telegram.commands_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        foreach (TelegramBot::where('enabled', true)->where('commands_enabled', true)->get() as $bot) {
            $lock = Cache::lock('telegram-poll:'.$bot->id, 45);
            if (! $lock->get()) {
                continue;
            }
            try {
                try {
                    $response = Http::connectTimeout(2)->timeout(5)->get('https://api.telegram.org/bot'.$bot->token.'/getUpdates', ['offset' => $bot->update_offset, 'limit' => 50, 'timeout' => 0, 'allowed_updates' => json_encode(['message'])]);
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
        $message = $update['message'] ?? [];
        $chat = (string) ($message['chat']['id'] ?? '');
        $user = (string) ($message['from']['id'] ?? '');
        $destination = TelegramDestination::where('telegram_bot_id', $bot->id)->where('chat_id', $chat)->where('enabled', true)->first();
        if (! $destination || $user === '' || ! in_array($user, array_map('strval', $destination->allowed_user_ids ?? []), true)) {
            return;
        }
        $key = 'telegram-command:'.$bot->id.':'.hash('sha256', $chat.':'.$user);
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return;
        } RateLimiter::hit($key, 60);
        $text = $message['text'] ?? '';
        if (! is_string($text) || ! preg_match('/^\/(today|tomorrow|student|stats)(?:@[A-Za-z0-9_]+)?(?:\s+(.{1,120}))?$/u', trim($text), $matches)) {
            return;
        }
        $command = $matches[1];
        if (! in_array($command, $bot->commands ?? [], true)) {
            return;
        }
        $argument = trim($matches[2] ?? '');
        $personal = $destination->detail_level === 'personal';
        $reply = match ($command) {
            'today' => $this->read->sessions(false, $personal),'tomorrow' => $this->read->sessions(true, $personal),'stats' => in_array($argument, ['7d', '30d'], true) ? $this->read->stats((int) $argument) : 'Use /stats 7d or /stats 30d.', 'student' => $personal ? $this->student($argument) : 'Student lookups require a trusted personal-detail destination.'
        };
        $this->delivery->direct($destination, $this->templates->split($reply), 'update:'.(string) $update['update_id'], 'command_'.$command);
    }

    private function student(string $name): string
    {
        if ($name === '') {
            return 'Use /student Exact Full Name.';
        }
        $students = Student::where('name_normalized', app(StudentIdentityService::class)->normalizeName($name))->limit(2)->get();
        if ($students->count() !== 1) {
            return 'No unique student match. Use the exact full name or review students in the admin portal.';
        }
        $student = $students->first();
        $lines = [$student->name];
        foreach (StudentPackage::where('student_id', $student->id)->where('status', 'active')->get() as $package) {
            $data = $this->read->package($package);
            $lines[] = $data['package_name'].': '.$data['remaining_credits'].' credits; expiry '.$data['expiry_date'];
        }
        $lines[] = 'Admin: '.route('admin.students.show', $student->id);

        return implode("\n", $lines);
    }
}
