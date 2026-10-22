<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Models\TelegramBot;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Reporting\Services\ReportPeriod;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramNavigationService
{
    public function __construct(private TelegramCommandCatalog $catalog, private TelegramBusinessReadService $business, private TelegramAnalyticsService $analytics, private ReportPeriod $periods) {}

    /** @return array<string, mixed> */
    public function state(string $command, string $argument): array
    {
        $state = ['command' => $command, 'range' => '7d', 'page' => 0];
        if ($command === 'student') {
            $state['query'] = $argument;
        } elseif (in_array($command, ['stats', 'games', 'resources', 'money'], true)) {
            if ($argument !== '') {
                if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{4}-\d{2}-\d{2})$/', $argument, $dates)) {
                    $state += ['start_date' => $dates[1], 'end_date' => $dates[2]];
                    $state['range'] = 'custom';
                } else {
                    $state['range'] = $argument;
                }
            }
            if (in_array($command, ['games', 'resources'], true)) {
                $state['section'] = $command;
            }
        }

        return $state;
    }

    /** @param array<string, mixed> $state
     * @return array{text: string, reply_markup: array<string, mixed>}
     */
    public function render(TelegramBot $bot, TelegramDestination $destination, string $user, array $state): array
    {
        $command = $state['command'];
        $personal = $destination->detail_level === 'personal';
        if (! $personal && in_array($command, ['student', 'money', 'brief', 'wrap'], true)) {
            $page = ['text' => $command === 'student' ? 'Student lookups require a trusted personal-detail destination.' : 'This report requires a trusted personal-detail destination.', 'buttons' => []];
        } elseif (in_array($command, ['start', 'help'], true)) {
            $page = ['text' => '<b>🏠 Business dashboard</b>'."\n".e(app(TimezoneService::class)->getBusinessTimezone())."\n\nChoose what you need.".($command === 'help' ? "\n\nFind a student: /student name\nReports: today, yesterday, 7d, 30d, or two dates.\nExample: /stats 2026-10-01 2026-10-14\nUp to 90 days, ending today or earlier.\n\nEnable more options in the website Telegram settings." : ''), 'buttons' => []];
            foreach ($this->catalog->all() as $name => $label) {
                if ($this->catalog->allows($bot, $name) && ($personal || ! in_array($name, ['student', 'money', 'brief', 'wrap'], true))) {
                    $page['buttons'][] = ['label' => $label, 'command' => $name, 'state' => in_array($name, ['games', 'resources'], true) ? ['section' => $name] : []];
                }
            }
        } elseif (in_array($command, ['stats', 'games', 'resources', 'money'], true)) {
            [$start, $end] = $this->bounds($state);
            if (! empty($state['period_picker'])) {
                $page = ['text' => '<b>📅 Date range</b>'."\n".app(TelegramReportFormatter::class)->period($start, $end)."\n\nChoose a period below.\n\nFor custom dates, send:\n/".$command.' YYYY-MM-DD YYYY-MM-DD'."\nUp to 90 days, ending today or earlier.", 'buttons' => []];
                foreach (['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7 days', '30d' => '30 days'] as $range => $label) {
                    $page['buttons'][] = ['label' => $label, 'state' => ['range' => $range, 'start_date' => null, 'end_date' => null, 'page' => 0, 'period_picker' => false], 'row' => 'periods'];
                }
                $page['buttons'][] = ['label' => '← Back to report', 'state' => ['period_picker' => false], 'row' => 'back'];
            } else {
                $page = $command === 'money' ? $this->business->money($start, $end) : $this->analytics->page($start, $end, $state);
                if ($command === 'money') {
                    [$heading, $body] = explode("\n", $page['text'], 2);
                    $page['text'] = $heading."\n".app(TelegramReportFormatter::class)->period($start, $end)."\n\n".$body;
                }
                $page['buttons'][] = ['label' => '📅 Date range', 'state' => ['period_picker' => true], 'row' => 'period'];
            }
        } elseif (in_array($command, ['today', 'tomorrow', 'week'], true)) {
            $page = $this->business->schedule($command === 'week' ? 7 : 1, $command === 'tomorrow' ? 1 : 0, $personal, (int) ($state['page'] ?? 0));
            foreach (['today' => 'Today', 'tomorrow' => 'Tomorrow', 'week' => 'Next 7 days'] as $name => $label) {
                $page['buttons'][] = ['label' => $label, 'command' => $name, 'state' => ['page' => 0]];
            }
        } elseif ($command === 'student') {
            $page = $this->business->student($state);
        } elseif ($command === 'attention') {
            $page = $this->business->attention($personal, (string) ($state['category'] ?? ''), (int) ($state['page'] ?? 0), (bool) ($state['include_clear'] ?? false));
        } elseif ($command === 'status') {
            $page = $this->business->status();
        } else {
            $page = ['text' => $this->business->briefing($command, $personal),
                'buttons' => [['label' => 'Needs attention', 'command' => 'attention'], ['label' => 'Today', 'command' => 'today'], ['label' => 'Money', 'command' => 'money']]];
        }
        $page['buttons'][] = ['label' => '↻ Refresh', 'state' => [], 'row' => 'footer'];
        if (! in_array($command, ['start', 'help'], true)) {
            $page['buttons'][] = ['label' => '🏠 Home', 'command' => 'start', 'row' => 'footer'];
        }
        $keyboard = [];
        $urls = [];
        foreach ($page['buttons'] as $button) {
            if (isset($button['url'])) {
                if (in_array($button['url'], $urls, true)) {
                    continue;
                }
                $urls[] = $button['url'];
                $keyboard[$button['row'] ?? 'content'][] = ['text' => $button['label'], 'url' => $button['url']];

                continue;
            }
            $target = $button['command'] ?? $command;
            if (! $this->catalog->allows($bot, $target)) {
                continue;
            }
            $base = $target === $command ? $state : array_intersect_key($state, array_flip(['range', 'start_date', 'end_date']));
            $next = array_replace($base, $button['state'] ?? [], ['command' => $target]);
            $token = Str::random(32);
            $context = ['bot' => $bot->id, 'destination' => $destination->id, 'user' => $user, 'state' => $next];
            Cache::put('telegram-navigation:'.$token, Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), now('UTC')->addMinutes(30));
            $keyboard[$button['row'] ?? 'content'][] = ['text' => $button['label'], 'callback_data' => 'nav:'.$token];
        }
        $rows = [];
        foreach ($keyboard as $group) {
            $row = [];
            foreach ($group as $button) {
                $wide = mb_strlen($button['text']) > 18;
                if ($row !== [] && ($wide || count($row) === 2)) {
                    $rows[] = $row;
                    $row = [];
                }
                $row[] = $button;
                if ($wide) {
                    $rows[] = $row;
                    $row = [];
                }
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return ['text' => $page['text'], 'reply_markup' => ['inline_keyboard' => $rows]];
    }

    /** @return array<string, mixed>|null */
    public function callback(TelegramBot $bot, TelegramDestination $destination, string $user, string $data): ?array
    {
        if (! preg_match('/^nav:([A-Za-z0-9]{32})$/', $data, $matches)) {
            return null;
        }
        $encrypted = Cache::get('telegram-navigation:'.$matches[1]);
        if (! is_string($encrypted)) {
            return null;
        }
        try {
            $context = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            if ($context['bot'] !== $bot->id || $context['destination'] !== $destination->id || $context['user'] !== $user
                || ! $this->catalog->allows($bot, $context['state']['command'])) {
                return null;
            }

            return $context['state'];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $state
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function bounds(array $state): array
    {
        if (($state['range'] ?? '') === 'yesterday') {
            $date = CarbonImmutable::yesterday(app(TimezoneService::class)->getBusinessTimezone())->toDateString();
            $state = ['range' => 'custom', 'start_date' => $date, 'end_date' => $date];
        }
        $filters = $this->periods->filters(Request::create('/', 'GET', array_intersect_key($state, array_flip(['range', 'start_date', 'end_date']))));
        [$start, $end] = $this->periods->bounds($filters);
        if ($start->diffInDays($end) > 90 || $end->gt(CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->endOfDay())) {
            throw ValidationException::withMessages(['range' => 'Choose at most 90 days, ending today or earlier.']);
        }

        return [$start, $end];
    }
}
