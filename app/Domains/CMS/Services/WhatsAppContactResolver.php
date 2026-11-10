<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Setting;

class WhatsAppContactResolver
{
    /** @return array{destination: string, label: string, locale: string}|null */
    public function resolve(string $context = 'public'): ?array
    {
        $enabled = (bool) Setting::get($context === 'portal' ? 'whatsapp_portal' : 'whatsapp_public', false);
        $destination = (string) Setting::get('whatsapp_url', '');
        $locale = in_array(app()->getLocale(), ['en', 'fr', 'de'], true) ? app()->getLocale() : 'en';
        $defaults = ['en' => 'Chat with Abdallah', 'fr' => 'Contacter Abdallah', 'de' => 'Abdallah kontaktieren'];
        $labels = (array) Setting::get('whatsapp_label', []);
        $messages = (array) Setting::get('whatsapp_message', []);
        $label = ($labels[$locale] ?? '') ?: $defaults[$locale];
        $message = $messages[$locale] ?? '';

        if (! $enabled || ! preg_match('~^https://(?:wa\.me/[1-9][0-9]{6,14}|api\.whatsapp\.com/send)(?:\?.*)?$~', $destination)) {
            return null;
        }

        if ($message !== '') {
            $parts = parse_url($destination);
            parse_str($parts['query'] ?? '', $query);
            $query['text'] = $message;
            $destination = 'https://'.$parts['host'].($parts['path'] ?? '').'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return ['destination' => $destination, 'label' => $label, 'locale' => $locale];
    }
}
