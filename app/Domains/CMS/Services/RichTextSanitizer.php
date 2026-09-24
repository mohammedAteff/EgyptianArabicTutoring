<?php

namespace App\Domains\CMS\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\URL;
use Mews\Purifier\Purifier;

class RichTextSanitizer
{
    public function __construct(private Purifier $purifier) {}

    public function sanitize(string $html): string
    {
        if (preg_match('/<img\b/i', $html)) {
            $html = $this->removeNonStorageImages($html);
        }

        return (string) $this->purifier->clean($html, [
            'HTML.Allowed' => 'h1,h2,h3,h4,p,b,i,strong,em,ul,ol,li,blockquote,a[href|title],img[src|alt],br',
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
            'Attr.EnableID' => false,
        ]);
    }

    private function removeNonStorageImages(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $images = iterator_to_array($document->getElementsByTagName('img'));
            foreach ($images as $image) {
                if (! $image instanceof DOMElement || ! $this->isSameOriginStorageUrl($image->getAttribute('src'))) {
                    $image?->parentNode?->removeChild($image);
                }
            }

            $body = $document->getElementsByTagName('body')->item(0);
            $cleanHtml = '';
            if ($body) {
                foreach ($body->childNodes as $child) {
                    $cleanHtml .= $document->saveHTML($child);
                }
            }

            return $cleanHtml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function isSameOriginStorageUrl(string $source): bool
    {
        $source = trim($source);
        $base = parse_url(URL::to('/storage/'));
        $parsed = parse_url($source);
        if (! $base || ! is_array($base) || ! $parsed || ! is_array($parsed)) {
            return false;
        }

        if (isset($parsed['scheme']) || isset($parsed['host'])) {
            if (! in_array(strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true)
                || strtolower((string) ($parsed['scheme'] ?? '')) !== strtolower((string) ($base['scheme'] ?? ''))
                || strtolower((string) ($parsed['host'] ?? '')) !== strtolower((string) ($base['host'] ?? ''))
                || isset($parsed['user']) || isset($parsed['pass'])) {
                return false;
            }
            if (isset($parsed['port']) && $parsed['port'] !== ($base['port'] ?? null)) {
                return false;
            }
        } elseif (! str_starts_with($source, '/')) {
            return false;
        }

        $path = (string) ($parsed['path'] ?? '');
        $fullyDecoded = false;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $decodedPath = rawurldecode($path);
            if ($decodedPath === $path) {
                $fullyDecoded = true;
                break;
            }

            $path = $decodedPath;
        }
        $path = str_replace('\\', '/', $path);
        $allowedPath = rtrim((string) ($base['path'] ?? '/storage/'), '/').'/';
        if (! $fullyDecoded
            || ! str_starts_with($path, $allowedPath)
            || preg_match('~(?:^|/)\.\.(?:/|$)~', $path)
            || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        return true;
    }
}
