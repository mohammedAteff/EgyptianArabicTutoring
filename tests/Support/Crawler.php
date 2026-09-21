<?php

namespace Symfony\Component\DomCrawler;

use ArrayIterator;
use Countable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use IteratorAggregate;
use Traversable;

class Crawler implements Countable, IteratorAggregate
{
    /** @var array<DOMNode> */
    protected array $nodes = [];

    protected ?DOMDocument $document = null;

    public function __construct(mixed $node = null)
    {
        if (is_string($node) && ! empty($node)) {
            $this->document = new DOMDocument;
            libxml_use_internal_errors(true);
            $this->document->loadHTML(mb_convert_encoding($node, 'HTML-ENTITIES', 'UTF-8'));
            libxml_clear_errors();
            $this->nodes = [$this->document->documentElement];
        } elseif ($node instanceof DOMNode) {
            $this->nodes = [$node];
            $this->document = $node instanceof DOMDocument ? $node : $node->ownerDocument;
        } elseif (is_array($node)) {
            $this->nodes = $node;
            if (! empty($node) && $node[0] instanceof DOMNode) {
                $this->document = $node[0]->ownerDocument;
            }
        } elseif ($node instanceof DOMNodeList) {
            foreach ($node as $item) {
                $this->nodes[] = $item;
            }
            if (! empty($this->nodes)) {
                $this->document = $this->nodes[0]->ownerDocument;
            }
        }
    }

    public function filter(string $selector): self
    {
        $xpathQuery = $this->cssToXPath($selector);

        return $this->filterXPath($xpathQuery);
    }

    public function filterXPath(string $xpathQuery): self
    {
        if (! $this->document) {
            return new self([]);
        }

        $xpath = new DOMXPath($this->document);
        $resultNodes = [];

        foreach ($this->nodes as $contextNode) {
            $nodeList = $xpath->query($xpathQuery, $contextNode);
            if ($nodeList) {
                foreach ($nodeList as $node) {
                    $resultNodes[] = $node;
                }
            }
        }

        return new self($resultNodes);
    }

    public function attr(string $attribute): ?string
    {
        $first = $this->nodes[0] ?? null;
        if ($first instanceof DOMElement) {
            return $first->hasAttribute($attribute) ? $first->getAttribute($attribute) : null;
        }

        return null;
    }

    public function text(): string
    {
        $first = $this->nodes[0] ?? null;

        return $first ? trim($first->textContent) : '';
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->nodes);
    }

    protected function cssToXPath(string $selector): string
    {
        // Simple CSS to XPath converter for test assertions
        $parts = explode(' ', trim($selector));
        $xpathParts = [];

        foreach ($parts as $part) {
            $tag = '*';
            $predicates = [];

            // Match tag name at start if present
            if (preg_match('/^([a-zA-Z0-9_-]+)/', $part, $m)) {
                $tag = $m[1];
                $part = substr($part, strlen($tag));
            }

            // Match attribute selectors like [attr="val"], [attr*="val"], [attr]
            if (preg_match_all('/\[([a-zA-Z0-9_-]+)([\*~|^$]?=)?(["\']?)([^\]"\']*)\3\]/', $part, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $attr = $match[1];
                    $op = $match[2] ?? '';
                    $val = $match[4] ?? '';

                    if ($op === '*=') {
                        $predicates[] = "contains(@{$attr}, '{$val}')";
                    } elseif ($op === '=') {
                        $predicates[] = "@{$attr}='{$val}'";
                    } else {
                        $predicates[] = "@{$attr}";
                    }
                }
            }

            $predicateString = ! empty($predicates) ? '['.implode(' and ', $predicates).']' : '';
            $xpathParts[] = ".//{$tag}{$predicateString}";
        }

        return implode('/', $xpathParts);
    }
}
