<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WebsiteCopyCatalog
{
    public const PAGES = ['home' => 'Homepage', 'about' => 'About Abdallah', 'pricing' => 'Lessons & Pricing', 'resources' => 'Resources', 'games' => 'Games', 'faq' => 'FAQ introduction', 'policy' => 'Booking & Refund Policy', 'blogs' => 'Blog introduction', 'shared' => 'Navigation, footer & shared wording', 'booking' => 'Booking instructions'];

    public const ROOTS = ['home' => ['home', 'home_expressions', 'home_approach_cards'], 'about' => ['about'], 'pricing' => ['pricing', 'tracks'], 'resources' => ['resources'], 'games' => ['games', 'game_feature'], 'faq' => ['faq_intro'], 'policy' => ['policy'], 'blogs' => [], 'shared' => [], 'booking' => []];

    public const SETTINGS = ['about_image_path', 'about_biography', 'about_philosophy', 'site_footer_text', 'booking_instructions', 'cancellation_policy', 'rescheduling_policy', 'home_cta_button', 'counters.learning_hours.headline', 'counters.learning_hours.subtitle', 'counters.monthly_traffic.template_visitors', 'counters.monthly_traffic.template_sessions', 'counters.monthly_traffic.template_page_views', 'counters.live_users.template'];

    /** @return array<string, mixed> */
    public function defaults(string $page): array
    {
        abort_unless(isset(self::PAGES[$page]), 404);
        $tree = [];
        foreach (self::ROOTS[$page] as $root) {
            $tree[$root] = config('public_content.'.$root);
        }
        $settings = Setting::query()->whereIn('key', array_merge(self::SETTINGS, Arr::flatten(config('public_content.home_controls'))))->pluck('value', 'key');
        if ($page === 'home') {
            foreach (config('public_content.home_controls') as $index => $controls) {
                foreach ($controls as $field => $setting) {
                    if ($settings->has($setting)) {
                        Arr::set($tree, 'home.'.$index.'.'.($field === 'intro' ? 'blocks.0.text' : $field), $settings[$setting]);
                    }
                }
            }
        }
        if ($page === 'about') {
            $about = Page::query()->where('slug', 'about')->where('status', 'published')->first();
            foreach ([0 => $settings['about_biography'] ?? $about?->content, 2 => $settings['about_philosophy'] ?? null] as $index => $body) {
                if (filled($body)) {
                    $tree['about'][$index]['blocks'] = [['type' => 'richtext', 'text' => (string) $body]];
                }
            }
            $tree['image'] = $about?->og_image_path ?? $settings['about_image_path'] ?? '';
        }
        if ($page === 'shared') {
            $tree['footer'] = $settings['site_footer_text'] ?? config('public_content.footer');
            foreach (json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR) as $key => $value) {
                if (is_string($value) && in_array($key, config('website_copy_labels', []), true)) {
                    $tree['labels'][sha1($key)] = $value;
                }
            }
            foreach (['counters.learning_hours.headline' => 'Globally, students have put in...', 'counters.learning_hours.subtitle' => 'of practice time in the last 7 days', 'counters.monthly_traffic.template_visitors' => 'We welcomed {count} visitors last month', 'counters.monthly_traffic.template_sessions' => 'We had {count} website sessions last month', 'counters.monthly_traffic.template_page_views' => 'Our pages were viewed {count} times last month', 'counters.live_users.template' => '{count} active visitors online right now'] as $key => $default) {
                $tree['counters'][str_replace('.', '__', $key)] = $settings[$key] ?? $default;
            }
        }
        if ($page === 'home') {
            $tree['ui']['final_booking_button'] = $settings['home_cta_button'] ?? 'Book Your Diagnostic';
        }
        $tree['ui'] = array_merge($tree['ui'] ?? [], config('website_copy_text.'.$page, []));
        $tree['links'] = array_fill_keys(config('website_copy_links.'.$page, []), '');
        $tree['seo'] = ['title' => '', 'description' => ''];

        return $tree;
    }

    /** @return array<string, array{path: string, label: string, group: string, type: string, default: mixed}> */
    public function fields(string $page): array
    {
        $tree = $this->defaults($page);
        $fields = [];
        $labels = $page === 'shared' ? json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR) : [];
        $labelNames = [];
        foreach ($labels as $key => $value) {
            $labelNames[sha1($key)] = $key;
        }
        foreach (Arr::dot($tree) as $path => $value) {
            if (! is_string($value) || str_ends_with($path, '.type')) {
                continue;
            }
            $parts = explode('.', $path);
            $group = $parts[0];
            if (isset($tree[$parts[0]][$parts[1] ?? '']['title'])) {
                $group = $tree[$parts[0]][$parts[1]]['title'];
            }
            $type = str_contains($path, '.blocks.') && str_ends_with($path, '.text') ? 'richtext' : (strlen($value) > 150 || str_contains($path, 'paragraph') || str_contains($path, 'description') ? 'textarea' : 'text');
            if ($path === 'image') {
                $type = 'image';
            }
            if (str_starts_with($path, 'links.')) {
                $type = 'url';
            }
            $label = $labelNames[$parts[1] ?? ''] ?? (str_starts_with($path, 'ui.') ? $value : Str::headline(str_replace('.', ' / ', $path)));
            if (str_contains($path, '.blocks.')) {
                $blockType = Arr::get($tree, substr($path, 0, -strlen('.text')).'.type');
                $type = $blockType === 'heading' ? 'text' : 'richtext';
                $label = ($blockType === 'heading' ? 'Subheading ' : 'Paragraph ').((int) ($parts[count($parts) - 2] ?? 0) + 1);
            } elseif (in_array(end($parts), ['title', 'badge', 'intro'], true)) {
                $label = Str::headline(end($parts));
            }
            $fields[$this->token($path)] = ['path' => $path, 'label' => $label, 'group' => Str::headline($group), 'type' => $type, 'default' => $value];
        }
        foreach (self::ROOTS[$page] as $root) {
            foreach ($tree[$root] ?? [] as $index => $section) {
                if (is_int($index) && is_array($section) && isset($section['blocks'], $section['title'])) {
                    foreach (['visible' => ['checkbox', true], 'order' => ['number', $index + 1], 'extra' => ['richtext', '']] as $control => [$type, $default]) {
                        $path = '_sections.'.$root.'.'.$index.'.'.$control;
                        $fields[$this->token($path)] = ['path' => $path, 'label' => match ($control) {
                            'visible' => 'Show this section', 'order' => 'Section position', 'extra' => 'Additional paragraphs, lists or links'
                        }, 'group' => Str::headline($section['title']), 'type' => $type, 'default' => $default];
                    }
                }
            }
        }

        return $fields;
    }

    public function token(string $path): string
    {
        return str_replace('.', '__', $path);
    }
}
