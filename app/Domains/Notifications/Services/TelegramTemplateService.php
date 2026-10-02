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
            $private = in_array($field, ['student_name', 'email', 'phone', 'meeting_url', 'student_time'], true);
            $replacements['{'.$field.'}'] = $private && ! $personal ? '[private]' : (string) ($values[$field] ?? 'Unknown');
        }

        return strtr($template, $replacements);
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
