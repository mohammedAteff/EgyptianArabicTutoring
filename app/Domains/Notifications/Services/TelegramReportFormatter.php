<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\HtmlString;

class TelegramReportFormatter
{
    /** @return array{text: string, entities: list<array{type: string, offset: int, length: int, url?: string}>} */
    public function format(string $report): array
    {
        $text = '';
        $entities = [];
        $position = 0;
        preg_match_all('/<b>([^<]*)<\/b>|<a href="([^"]*)">([^<]*)<\/a>/u', $report, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($matches as $match) {
            $text .= html_entity_decode(substr($report, $position, $match[0][1] - $position), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $bold = str_starts_with($match[0][0], '<b>');
            $label = html_entity_decode($bold ? $match[1][0] : $match[3][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $entity = ['type' => $bold ? 'bold' : 'text_link', 'offset' => $this->units($text), 'length' => $this->units($label)];
            if (! $bold) {
                $entity['url'] = html_entity_decode($match[2][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if ($label !== '' && ($bold || preg_match('~^https?://~i', $entity['url']))) {
                $entities[] = $entity;
            }
            $text .= $label;
            $position = $match[0][1] + strlen($match[0][0]);
        }
        $text .= html_entity_decode(substr($report, $position), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return ['text' => $text, 'entities' => $entities];
    }

    /**
     * @param  list<array{type: string, offset: int, length: int, url?: string}>  $entities
     * @return array{parts: list<string>, entities: list<list<array{type: string, offset: int, length: int, url?: string}>>}
     */
    public function split(string $text, array $entities = []): array
    {
        $parts = [];
        $partEntities = [];
        $offset = 0;
        while ($text !== '') {
            $length = min(3500, mb_strlen($text));
            $part = mb_substr($text, 0, $length);
            while ($this->units($part) > 3500) {
                $part = mb_substr($text, 0, --$length);
            }
            if ($length < mb_strlen($text) && ($newline = mb_strrpos($part, "\n")) !== false && $newline > $length / 2) {
                $part = mb_substr($part, 0, $newline + 1);
                $length = mb_strlen($part);
            }
            $end = $offset + $this->units($part);
            $current = [];
            foreach ($entities as $entity) {
                $start = max($offset, $entity['offset']);
                $stop = min($end, $entity['offset'] + $entity['length']);
                if ($start < $stop) {
                    $current[] = array_merge($entity, ['offset' => $start - $offset, 'length' => $stop - $start]);
                }
            }
            $parts[] = $part;
            $partEntities[] = $current;
            $offset = $end;
            $text = mb_substr($text, $length);
        }

        return ['parts' => $parts, 'entities' => $partEntities];
    }

    public function units(string $text): int
    {
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    public function comparison(string $label, int|float $current, int|float $previous): string
    {
        $change = $current - $previous;
        if ($change == 0) {
            return $label.': '.$current.' · unchanged';
        }
        $percentage = $previous > 0 ? ' ('.($change >= 0 ? '+' : '').round(100 * $change / $previous, 1).'%)' : ' · new activity';

        return $label.': '.$current.' · '.($change >= 0 ? '+' : '').$change.$percentage;
    }

    public function period(CarbonImmutable $start, CarbonImmutable $end): string
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $from = $start->setTimezone($timezone)->format('j M Y');
        $through = $end->setTimezone($timezone)->format('j M Y');

        return $from.($from === $through ? '' : ' – '.$through)."\n".e($timezone);
    }

    /** @param array<string, array{label: string, remaining: int, available: int}> $balances */
    public function credits(array $balances): string
    {
        $lines = [];
        foreach ($balances as $code => $balance) {
            if ($code === 'legacy_unclassified') {
                continue;
            }
            $label = match ($code) {
                'one_hour' => '1-hour', 'two_hour' => '2-hour', default => $balance['label']
            };
            $lines[] = $label.': '.$balance['available'];
        }

        return implode(' · ', $lines).' available';
    }

    /** @param list<array{type: string, offset: int, length: int, url?: string}> $entities */
    public function previewHtml(string $text, array $entities): HtmlString
    {
        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        $html = '';
        $position = 0;
        foreach ($entities as $entity) {
            if ($entity['offset'] < $position || ! in_array($entity['type'], ['bold', 'text_link'], true)) {
                continue;
            }
            $html .= e(mb_convert_encoding(substr($utf16, $position * 2, ($entity['offset'] - $position) * 2), 'UTF-8', 'UTF-16LE'));
            $label = e(mb_convert_encoding(substr($utf16, $entity['offset'] * 2, $entity['length'] * 2), 'UTF-8', 'UTF-16LE'));
            $url = $entity['url'] ?? '';
            $html .= $entity['type'] === 'bold' ? '<strong>'.$label.'</strong>' : (preg_match('~^https?://~i', $url) ? '<a class="text-sky-700 underline" href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.$label.'</a>' : $label);
            $position = $entity['offset'] + $entity['length'];
        }
        $html .= e(mb_convert_encoding(substr($utf16, $position * 2), 'UTF-8', 'UTF-16LE'));

        return new HtmlString($html);
    }

    public function country(?string $code): string
    {
        $code = strtoupper($code ?? 'ZZ');
        $display = app(TimezoneDisplayService::class);
        if (! preg_match('/^[A-Z]{2}$/', $code) || in_array($code, ['ZZ', 'XX'], true) || $display->resolveCountryName($code) === $code) {
            return '🌐 Unknown country';
        }

        return mb_chr(127397 + ord($code[0])).mb_chr(127397 + ord($code[1])).' '.$display->resolveCountryName($code);
    }

    public function expiry(StudentPackage $package): string
    {
        if ($package->validity_days !== null && $package->funding_activated_at === null) {
            return 'Not started · awaiting first payment';
        }
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $deadline = $package->expiryDeadline();

        return $deadline ? $deadline->setTimezone($timezone)->format('j M Y, H:i').' · '.$timezone : 'No expiry';
    }
}
