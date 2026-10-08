<?php

namespace App\Domains\Lms\Services;

use App\Domains\CMS\Services\RichTextSanitizer;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LmsAsset;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Resources\Models\Resource;
use App\Rules\SafeLessonUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LmsContentService
{
    public const AUTHORABLE = ['rich_text', 'image', 'file', 'resource', 'external_link', 'youtube_video', 'external_video', 'video', 'quiz', 'assignment'];

    public function __construct(private RichTextSanitizer $sanitizer) {}

    public function sanitizeText(string $html): string
    {
        return preg_replace('/<img\b[^>]*>/i', '', $this->sanitizer->sanitize($html)) ?? '';
    }

    /** @param array<string,mixed> $data
     * @param  list<int>  $allowedAssetIds
     * @param  list<int>  $allowedVideoIds
     * @return array{kind:string,status:string,resource_id:?int,asset_id:?int,video_asset_id?:int|null,payload:?array<string,mixed>}
     */
    public function normalize(Course $course, array $data, array $allowedAssetIds = [], array $allowedVideoIds = []): array
    {
        if (in_array($data['kind'] ?? null, ['quiz', 'assignment'], true)) {
            $definition = $data['definition'] ?? [];
            if (is_string($definition)) {
                try {
                    $definition = json_decode($definition, true, 16, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $this->invalid('definition', 'Use a valid assessment definition.');
                }
            }
            if (! is_array($definition)) {
                $this->invalid('definition', 'Use an assessment definition object.');
            }

            return ['kind' => $data['kind'], 'status' => 'ready', 'resource_id' => null, 'asset_id' => null,
                'video_asset_id' => null, 'payload' => app(LmsLearningDefinition::class)->assessment($data['kind'], $definition)];
        }
        $values = Validator::make($data, ['kind' => ['required', Rule::in(self::AUTHORABLE)],
            'html' => ['nullable', 'string', 'max:50000'], 'url' => ['nullable', 'string', 'max:2000'],
            'resource_id' => ['nullable', 'integer', 'min:1'], 'asset_id' => ['nullable', 'integer', 'min:1'],
            'video_asset_id' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', Rule::in(['resource', 'asset'])],
            'alt' => ['nullable', 'string', 'max:300'], 'label' => ['nullable', 'string', 'max:300']])->validate();
        $kind = $values['kind'];
        $payload = null;
        $resourceId = null;
        $assetId = null;
        if ($kind === 'video') {
            $video = VideoAsset::query()->with('course')->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->find($values['video_asset_id'] ?? 0);
            if (! $video || ! $video->usableFor($course)
                || ((int) $video->course_id !== (int) $course->id && ! in_array((int) $video->id, $allowedVideoIds, true))) {
                $this->invalid('video_asset_id', 'Choose ready media belonging to this course draft.');
            }

            return ['kind' => 'video', 'status' => 'ready', 'resource_id' => null, 'asset_id' => null, 'video_asset_id' => (int) $video->id, 'payload' => null];
        } elseif ($kind === 'resource' || ($kind === 'file' && ($values['source'] ?? 'asset') === 'resource')) {
            if (empty($values['resource_id'])) {
                $this->invalid('resource_id', 'Choose a published Resource from the library.');
            }
            $resource = Resource::query()->published()->find($values['resource_id']);
            if (! $resource) {
                $this->invalid('resource_id', 'This Resource is unavailable. Choose a published Resource.');
            }
            if ($kind === 'file' && ! $resource->file_path) {
                $this->invalid('resource_id', 'Choose a Resource with an attached file.');
            }
            $resourceId = (int) $resource->id;
            if ($kind === 'file') {
                $payload = ['label' => $values['label'] ?? 'Download file'];
            }
        } elseif (in_array($kind, ['image', 'file'], true)) {
            if (empty($values['asset_id'])) {
                $this->invalid('asset_id', 'Upload or choose an attachment for this course.');
            }
            $asset = LmsAsset::query()->with('course')->find($values['asset_id']);
            if (! $asset || $asset->kind !== $kind || ! $asset->usableFor($course)
                || ((int) $asset->course_id !== (int) $course->id && ! in_array((int) $asset->id, $allowedAssetIds, true))) {
                $this->invalid('asset_id', 'This attachment does not belong to this course draft.');
            }
            $assetId = (int) $asset->id;
            if ($kind === 'image') {
                if (trim($values['alt'] ?? '') === '') {
                    $this->invalid('alt', 'Describe the image for readers who cannot see it.');
                }
                $payload = ['alt' => $values['alt']];
            } else {
                $payload = ['label' => $values['label'] ?? 'Download file'];
            }
        } elseif ($kind === 'rich_text') {
            $html = $values['html'] ?? '';
            $clean = $this->sanitizeText($html);
            if (trim(strip_tags($clean)) === '') {
                $this->invalid('html', 'Add lesson text. Use an Image block for images.');
            }
            $payload = ['html' => $clean];
        } else {
            $url = $values['url'] ?? '';
            if (! SafeLessonUrl::isSafe($url)) {
                $this->invalid('url', 'Use a valid HTTPS link without embedded login credentials.');
            }
            $payload = match ($kind) {
                'youtube_video' => $this->youtube($url),
                'external_video' => $this->externalVideo($url),
                default => ['url' => $url],
            };
        }

        return ['kind' => $kind, 'status' => 'ready', 'resource_id' => $resourceId, 'asset_id' => $assetId, 'payload' => $payload];
    }

    /** @param array<string,mixed> $block
     * @return array<string,mixed> */
    public function input(array $block): array
    {
        $payload = $block['payload'] ?? [];
        if (! is_array($payload)) {
            $this->invalid('content', 'This content block needs to be updated before preview or publication.');
        }

        return ['kind' => $block['kind'], 'resource_id' => $block['resource_id'] ?? null, 'asset_id' => $block['asset_id'] ?? null, 'video_asset_id' => $block['video_asset_id'] ?? null,
            'source' => ! empty($block['resource_id']) ? 'resource' : 'asset', 'html' => $payload['html'] ?? null,
            'url' => $payload['url'] ?? null, 'alt' => $payload['alt'] ?? null, 'label' => $payload['label'] ?? null,
            'definition' => $payload];
    }

    /** @return array{url:string,provider:string,embed_url:string} */
    private function youtube(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $id = null;
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if ($path === '/watch') {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})/?$~D', $path, $match)) {
                $id = $match[1];
            }
        } elseif ($host === 'youtu.be' && preg_match('~^/([A-Za-z0-9_-]{11})/?$~D', $path, $match)) {
            $id = $match[1];
        }
        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
            $this->invalid('url', 'Use a YouTube watch, Shorts, share or embed link.');
        }

        return ['url' => 'https://www.youtube.com/watch?v='.$id, 'provider' => 'youtube', 'embed_url' => 'https://www.youtube-nocookie.com/embed/'.$id];
    }

    /** @return array<string,string> */
    private function externalVideo(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true) && preg_match('~^/(?:video/)?([0-9]{1,12})/?$~D', $path, $match)) {
            return ['url' => 'https://vimeo.com/'.$match[1], 'provider' => 'vimeo', 'embed_url' => 'https://player.vimeo.com/video/'.$match[1]];
        }
        if (! preg_match('/\.(?:mp4|webm|ogv)$/iD', $path)) {
            $this->invalid('url', 'Use a Vimeo video link or a direct HTTPS MP4, WebM or OGV file.');
        }

        return ['url' => $url, 'provider' => 'direct'];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
