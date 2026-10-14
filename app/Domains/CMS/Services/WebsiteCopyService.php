<?php

namespace App\Domains\CMS\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\CMS\Models\WebsiteCopy;
use App\Rules\SafeLessonUrl;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsiteCopyService
{
    public function __construct(private WebsiteCopyCatalog $catalog, private RichTextSanitizer $sanitizer, private AuditLogService $audit) {}

    /** @return array<string, mixed> */
    public function values(string $page, ?string $locale = null, bool $draft = false): array
    {
        $locale ??= app()->getLocale();
        $defaults = array_map(fn (array $field): mixed => $field['default'], $this->catalog->fields($page));
        $english = $this->document($page, 'en');
        $values = array_replace($defaults, $english?->published_content ?? []);
        if ($page === 'shared' && in_array($locale, ['fr', 'de'], true)) {
            $existing = json_decode(file_get_contents(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
            foreach (config('website_copy_labels', []) as $label) {
                if (isset($existing[$label])) {
                    $values['labels__'.sha1($label)] = $existing[$label];
                }
            }
        }
        $document = $locale === 'en' ? $english : $this->document($page, $locale);
        if ($locale !== 'en' && $document?->published_content !== null) {
            $values = array_replace($values, array_filter($document->published_content, fn (mixed $value): bool => $value !== '' && $value !== null));
        }
        if ($draft) {
            $revision = $document?->revisions()->where('status', 'draft')->latest('id')->first();
            $values = array_replace($values, $revision?->content['values'] ?? []);
        }

        return $values;
    }

    public function value(string $page, string $path, mixed $fallback = '', ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $token = $this->catalog->token($path);
        $document = $this->document($page, $locale);
        $values = $document?->published_content;
        if (request()->attributes->get('website_copy_preview', false)) {
            $values = $this->values($page, $locale, true);
        }
        if (array_key_exists($token, $values ?? []) && ($locale === 'en' || ($values[$token] !== '' && $values[$token] !== null))) {
            return $values[$token];
        }
        $english = $this->document($page, 'en');

        return $english?->published_content[$token] ?? $fallback;
    }

    public function document(string $page, string $locale): ?WebsiteCopy
    {
        $cache = request()->attributes->get('website_copy_documents');
        if ($cache === null) {
            $cache = WebsiteCopy::query()->get()->keyBy(fn (WebsiteCopy $copy): string => $copy->page.':'.$copy->locale);
            request()->attributes->set('website_copy_documents', $cache);
        }

        return $cache->get($page.':'.$locale);
    }

    public function sourceHash(string $page): string
    {
        return hash('sha256', json_encode($this->values($page, 'en'), JSON_THROW_ON_ERROR));
    }

    public function state(string $page, string $locale): string
    {
        $copy = $this->document($page, $locale);
        $draftId = $copy?->revisions()->where('status', 'draft')->max('id');

        return hash('sha256', json_encode([(int) ($copy?->version ?? 0), $draftId, $this->sourceHash($page)], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $values */
    public function save(Administrator $actor, string $page, string $locale, array $values, string $action, string $state): WebsiteCopy
    {
        $actor = Administrator::query()->whereKey($actor->id)->whereIn('role', ['super_admin', 'admin'])->whereNull('suspended_at')->firstOrFail();
        abort_unless(in_array($action, ['draft', 'publish'], true), 422);
        abort_unless(isset(WebsiteCopyCatalog::PAGES[$page]) && in_array($locale, ['en', 'fr', 'de'], true), 404);
        $fields = $this->catalog->fields($page);
        $rules = [];
        if (array_diff(array_keys($values), array_keys($fields)) !== []) {
            throw ValidationException::withMessages(['values' => 'An unsupported content field was submitted. Reload this editor.']);
        }
        foreach ($fields as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'checkbox' => ['required', 'boolean'], 'number' => ['required', 'integer', 'min:1', 'max:100'], default => ['present', 'nullable', 'string', 'max:20000']
            };
        }
        $values = validator($values, $rules)->validate();
        foreach ($values as $key => &$value) {
            if ($fields[$key]['type'] === 'richtext') {
                $value = $this->sanitizer->sanitize((string) $value);
            } elseif ($fields[$key]['type'] === 'image') {
                if ($value && app(AboutImageService::class)->url($value) === null) {
                    throw ValidationException::withMessages(['values.'.$key => 'Choose a valid public image path or HTTPS image URL.']);
                }
            } elseif ($fields[$key]['type'] === 'url') {
                $value = (string) ($value ?? '');
                if ($value !== '' && ! SafeLessonUrl::isSafe($value)
                    && ! (preg_match('~^/(?!/)[a-zA-Z0-9/_-]*$~D', $value))) {
                    throw ValidationException::withMessages(['values.'.$key => 'Use a safe HTTPS address or a website path such as /booking. Leave blank to keep the current destination.']);
                }
            } elseif (is_string($value) || $value === null) {
                $value = (string) ($value ?? '');
            }
        }
        unset($value);

        return DB::transaction(function () use ($actor, $page, $locale, $values, $action, $state): WebsiteCopy {
            WebsiteCopy::query()->firstOrCreate(['page' => $page, 'locale' => $locale]);
            $copy = WebsiteCopy::query()->where('page', $page)->where('locale', $locale)->lockForUpdate()->firstOrFail();
            if ($locale !== 'en') {
                WebsiteCopy::query()->where('page', $page)->where('locale', 'en')->lockForUpdate()->first();
            }
            request()->attributes->remove('website_copy_documents');
            if (! hash_equals($state, $this->state($page, $locale))) {
                throw ValidationException::withMessages(['state' => 'This content or its English source changed in another window. Reload and compare before saving.']);
            }
            $sourceHash = $this->sourceHash($page);
            if (! $copy->revisions()->exists()) {
                $copy->revisions()->create(['revision_number' => 1, 'title' => 'Original website content · '.strtoupper($locale),
                    'content' => ['values' => $this->values($page, $locale), 'source_hash' => $sourceHash],
                    'created_by_id' => $actor->id, 'status' => 'archived']);
            }
            $copy->revisions()->where('status', 'draft')->update(['status' => 'archived']);
            if ($action === 'publish') {
                $copy->revisions()->where('status', 'published')->update(['status' => 'archived']);
                $copy->forceFill(['published_content' => $values, 'published_at' => now(), 'source_hash' => $sourceHash, 'version' => $copy->version + 1])->save();
            }
            $copy->revisions()->create(['revision_number' => (int) $copy->revisions()->max('revision_number') + 1,
                'title' => WebsiteCopyCatalog::PAGES[$page].' · '.strtoupper($locale), 'content' => ['values' => $values, 'source_hash' => $sourceHash],
                'created_by_id' => $actor->id, 'status' => $action === 'publish' ? 'published' : 'draft']);
            $this->audit->log('website_copy_'.$action, WebsiteCopy::class, $copy->id, newData: ['page' => $page, 'locale' => $locale, 'version' => $copy->version], adminId: $actor->id);
            if ($action === 'publish') {
                DB::afterCommit(fn () => Cache::forget(EngagementCounterService::cacheKey()));
            }
            request()->attributes->remove('website_copy_documents');

            return $copy;
        });
    }

    /** @return array<string, mixed> Original configuration for restoration after this request. */
    public function apply(string $page, bool $preview = false): array
    {
        $original = config('public_content');
        $fields = $this->catalog->fields($page);
        $values = $this->values($page, draft: $preview);
        $tree = $this->catalog->defaults($page);
        foreach ($fields as $key => $field) {
            Arr::set($tree, $field['path'], $values[$key] ?? $field['default']);
        }
        foreach ($tree['_sections'] ?? [] as $root => $controls) {
            foreach ($controls as $index => $control) {
                $tree[$root][$index]['_visible'] = (bool) $control['visible'];
                $tree[$root][$index]['_order'] = (int) $control['order'];
                $tree[$root][$index]['extra_content'] = $control['extra'];
            }
        }
        foreach (WebsiteCopyCatalog::ROOTS[$page] as $root) {
            if (is_array($tree[$root]) && array_is_list($tree[$root])) {
                uasort($tree[$root], fn (array $a, array $b): int => ($a['_order'] ?? 0) <=> ($b['_order'] ?? 0));
            }
            config(['public_content.'.$root => $tree[$root]]);
        }
        request()->attributes->set('website_copy_page', $page);
        request()->attributes->set('website_copy_preview', $preview);
        request()->attributes->set('website_copy_tree', $tree);

        return $original;
    }
}
