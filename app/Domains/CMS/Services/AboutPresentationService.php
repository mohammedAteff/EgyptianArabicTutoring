<?php

namespace App\Domains\CMS\Services;

use DOMDocument;
use DOMElement;

class AboutPresentationService
{
    public function __construct(private RichTextSanitizer $sanitizer) {}

    /**
     * @param  array<int, array{type: string, text: string}>  $blocks
     * @return array{introduction: list<string>, groups: list<array{heading: string, blocks: list<string>}>}
     */
    public function present(array $blocks, string $variant = 'narrative', ?string $body = null): array
    {
        $introduction = [];
        $groups = [];
        $entries = [];
        $experienceLabels = $variant === 'experience' && $body === null && isset($blocks[1], $blocks[3], $blocks[5])
            && ! array_filter($blocks, fn (array $block): bool => $block['type'] !== 'paragraph' || preg_match('/<\/?[a-z][^>]*>/i', $block['text']))
            ? [1 => 'Live explanations', 3 => 'Materials to revisit', 5 => 'Between our lessons'] : [];

        if ($body !== null) {
            $entries = $this->bodyEntries($body, $variant === 'principles');
        } else {
            foreach ($blocks as $identity => $block) {
                if (isset($experienceLabels[$identity])) {
                    $entries[] = ['heading' => true, 'html' => e(website_text('about', $experienceLabels[$identity]))];
                }
                if ($block['type'] === 'heading') {
                    $entries[] = ['heading' => true, 'html' => e($block['text'])];
                } else {
                    array_push($entries, ...$this->bodyEntries($block['text'], $variant === 'principles'));
                }
            }
        }

        foreach ($entries as $entry) {
            if ($entry['heading']) {
                $heading = $variant === 'principles' ? $this->withoutNumber($entry['html']) : $entry['html'];
                $groups[] = ['heading' => $heading, 'blocks' => []];
            } elseif ($groups === []) {
                $introduction[] = $entry['html'];
            } else {
                $groups[array_key_last($groups)]['blocks'][] = $entry['html'];
            }
        }

        return compact('introduction', 'groups');
    }

    /** @return list<array{heading: bool, html: string}> */
    private function bodyEntries(string $body, bool $recognizePrinciples): array
    {
        $isHtml = preg_match('/<\/?[a-z][^>]*>/i', $body);
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $paragraphs = preg_split('/\n\s*\n/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $html = $isHtml ? $this->sanitizer->sanitize($body) : implode('', array_map(fn (string $text): string => '<p>'.nl2br(e(trim($text))).'</p>', $paragraphs));
        $candidateHtml = $html;
        if (! $isHtml && $recognizePrinciples) {
            $parts = preg_split('/^(\d{1,2}[.)]\h+\S[^\n]*)$/mu', $normalized, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $parts = array_merge(...array_map(fn (string $part): array => preg_split('/\n\s*\n/u', $part, -1, PREG_SPLIT_NO_EMPTY), $parts));
            $candidateHtml = implode('', array_map(fn (string $text): string => '<p>'.nl2br(e(trim($text))).'</p>', array_filter($parts, fn (string $text): bool => trim($text) !== '')));
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$candidateHtml.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $container = $document->getElementsByTagName('body')->item(0);
            $entries = [];
            $numbers = [];
            $semanticHeadings = 0;

            foreach ($container->childNodes ?? [] as $node) {
                if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
                    if ($entries !== []) {
                        $entries[array_key_last($entries)]['html'] .= $document->saveHTML($node);
                    }

                    continue;
                }
                $semantic = $recognizePrinciples && $node instanceof DOMElement && in_array($node->tagName, ['h1', 'h2', 'h3', 'h4'], true);
                $numbered = $recognizePrinciples && $node instanceof DOMElement && $node->tagName === 'p'
                    && ! $node->getElementsByTagName('br')->length
                    && preg_match('/^(\d{1,2})[.)]\h+\S[^\r\n]*$/u', trim($node->textContent), $match);
                $inner = '';
                if ($semantic || $numbered) {
                    foreach ($node->childNodes as $child) {
                        $inner .= $document->saveHTML($child);
                    }
                }
                $entries[] = ['heading' => $semantic || $numbered, 'html' => $semantic || $numbered ? $inner : $document->saveHTML($node)];
                if ($semantic) {
                    $semanticHeadings++;
                } elseif ($numbered) {
                    $numbers[] = (int) $match[1];
                }
            }

            if (! $recognizePrinciples) {
                return $entries;
            }

            $headingCount = $semanticHeadings + count($numbers);
            $reliable = $headingCount >= 2 && ($semanticHeadings === $headingCount || ($semanticHeadings === 0 && $numbers === range(1, $headingCount)));
            $hasSupportingContent = false;
            foreach (array_reverse($entries) as $entry) {
                if ($entry['heading']) {
                    $reliable = $reliable && $hasSupportingContent;
                    $hasSupportingContent = false;
                } elseif (trim(strip_tags($entry['html'])) !== '' || str_contains($entry['html'], '<img')) {
                    $hasSupportingContent = true;
                }
            }

            return $reliable ? $entries : [['heading' => false, 'html' => $html]];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function withoutNumber(string $heading): string
    {
        return preg_replace('/^((?:\s*<[^>]+>)*\s*)\d+[.)]\h+/u', '$1', $heading) ?? $heading;
    }
}
