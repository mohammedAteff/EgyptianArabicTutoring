<?php

namespace App\Domains\Notifications\Services;

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
}
