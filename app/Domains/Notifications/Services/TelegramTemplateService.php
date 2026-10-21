<?php

namespace App\Domains\Notifications\Services;

use Illuminate\Validation\ValidationException;

class TelegramTemplateService
{
    public function __construct(private TelegramRuleCatalog $catalog) {}

    public function validate(string $trigger, string $template): void
    {
        preg_match_all('/\{([^{}]+)\}/u', $template, $matches);
        $invalid = array_diff($matches[1], $this->catalog->get($trigger)['fields']);
        if ($invalid !== [] || str_contains(preg_replace('/\{[^{}]+\}/u', '', $template) ?? '', '{') || str_contains(preg_replace('/\{[^{}]+\}/u', '', $template) ?? '', '}')) {
            throw ValidationException::withMessages(['template' => 'Use only the listed placeholders for this alert.']);
        }
    }

    /** @param array<string, scalar|null> $values */
    public function render(string $trigger, string $template, array $values, bool $personal): string
    {
        $this->validate($trigger, $template);
        $replacements = [];
        foreach ($this->catalog->get($trigger)['fields'] as $field) {
            $private = in_array($field, ['student_name', 'email', 'phone', 'meeting_url', 'student_time', 'details', 'birthday', 'amount'], true);
            $value = (string) ($values[$field] ?? 'Unknown');
            if ($field === 'digest' && in_array($trigger, ['analytics_digest', 'business_digest'], true)) {
                $value = app(TelegramReportFormatter::class)->format($value)['text'];
            }
            $replacements['{'.$field.'}'] = $private && ! $personal ? '[private]' : $value;
        }

        return strtr($template, $replacements);
    }

    /**
     * @param  array<string, scalar|null>  $values
     * @return array{parts: list<string>, entities: list<list<array{type: string, offset: int, length: int, url?: string}>>}
     */
    public function deliveryParts(string $trigger, string $template, array $values, bool $personal): array
    {
        $this->validate($trigger, $template);
        $formatter = app(TelegramReportFormatter::class);
        $text = '';
        $entities = [];
        foreach (preg_split('/(\{[^{}]+\})/u', $template, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $segment) {
            if ($segment === '{digest}' && in_array($trigger, ['analytics_digest', 'business_digest'], true)) {
                $formatted = $formatter->format((string) ($values['digest'] ?? 'Unknown'));
                $offset = $formatter->units($text);
                foreach ($formatted['entities'] as $entity) {
                    $entities[] = array_merge($entity, ['offset' => $entity['offset'] + $offset]);
                }
                $text .= $formatted['text'];
            } else {
                $text .= $this->render($trigger, $segment, $values, $personal);
            }
        }

        return $formatter->split($text, $entities);
    }

    /** @return list<string> */
    public function split(string $text): array
    {
        $parts = [];
        while (mb_strlen($text) > 3500) {
            $parts[] = mb_substr($text, 0, 3500);
            $text = mb_substr($text, 3500);
        }
        if ($text !== '') {
            $parts[] = $text;
        }

        return $parts;
    }
}
