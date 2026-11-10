<?php

namespace App\Domains\CMS\Services;

class LegalDocumentPresenter
{
    /** @return array{introduction: list<array{type: string, text: string, html: bool}>, sections: list<array{id: string, number: string, title: string, blocks: list<array{type: string, text: string, html: bool}>}>, needsReview: bool} */
    public function fromText(?string $content): array
    {
        $blocks = [];
        $paragraph = [];
        foreach (preg_split('/\R/u', trim($content ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^\d+\.\s+(?:\[[TP]-\d+\]\s*)?\S/u', $line)) {
                if ($paragraph !== []) {
                    $blocks[] = ['type' => 'paragraph', 'text' => implode("\n", $paragraph)];
                    $paragraph = [];
                }
                if ($line !== '') {
                    $blocks[] = ['type' => 'heading', 'text' => $line];
                }
            } else {
                $paragraph[] = $line;
            }
        }
        if ($paragraph !== []) {
            $blocks[] = ['type' => 'paragraph', 'text' => implode("\n", $paragraph)];
        }

        return $this->present($blocks, false);
    }

    /**
     * @param  list<array{type: string, text: string}>  $blocks
     * @return array{introduction: list<array{type: string, text: string, html: bool}>, sections: list<array{id: string, number: string, title: string, blocks: list<array{type: string, text: string, html: bool}>}>, needsReview: bool}
     */
    public function fromBlocks(array $blocks): array
    {
        return $this->present($blocks, true);
    }

    /**
     * @param  list<array{type: string, text: string}>  $blocks
     * @return array{introduction: list<array{type: string, text: string, html: bool}>, sections: list<array{id: string, number: string, title: string, blocks: list<array{type: string, text: string, html: bool}>}>, needsReview: bool}
     */
    private function present(array $blocks, bool $allowSanitizedHtml): array
    {
        /** @var array{introduction: list<array{type: string, text: string, html: bool}>, sections: list<array{id: string, number: string, title: string, blocks: list<array{type: string, text: string, html: bool}>}>, needsReview: bool} $document */
        $document = ['introduction' => [], 'sections' => [], 'needsReview' => false];
        foreach ($blocks as $block) {
            $text = trim($block['text']);
            if ($text === '') {
                continue;
            }
            $document['needsReview'] = $document['needsReview'] || str_contains($text, '[CONFIRM:');
            if ($block['type'] === 'heading' && preg_match('/^(\d+)\.\s+(?:\[[TP]-\d+\]\s*)?(.+)$/u', $text, $heading)) {
                $document['sections'][] = ['id' => 'legal-section-'.(count($document['sections']) + 1), 'number' => $heading[1], 'title' => $heading[2], 'blocks' => []];

                continue;
            }
            $presented = ['type' => $block['type'] === 'heading' ? 'heading' : 'paragraph', 'text' => $text,
                'html' => $allowSanitizedHtml && (bool) preg_match('/<\/?[a-z][^>]*>/i', $text)];
            if ($document['sections'] === []) {
                $document['introduction'][] = $presented;
            } else {
                $document['sections'][array_key_last($document['sections'])]['blocks'][] = $presented;
            }
        }

        return $document;
    }
}
