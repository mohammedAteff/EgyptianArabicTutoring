@props(['context' => 'public'])
@php
    $setting = \App\Domains\CMS\Models\Setting::class;
    $enabled = (bool) $setting::get($context === 'portal' ? 'whatsapp_portal' : 'whatsapp_public', false);
    $destination = (string) $setting::get('whatsapp_url', '');
    $locale = in_array(app()->getLocale(), ['en','fr','de'], true) ? app()->getLocale() : 'en';
    $defaults = ['en' => 'Chat with Abdallah', 'fr' => 'Contacter Abdallah', 'de' => 'Abdallah kontaktieren'];
    $labels = (array) $setting::get('whatsapp_label', []);
    $messages = (array) $setting::get('whatsapp_message', []);
    $label = $labels[$locale] ?? $defaults[$locale];
    $message = $messages[$locale] ?? '';
    $valid = preg_match('~^https://(?:wa\.me/[1-9][0-9]{6,14}|api\.whatsapp\.com/send)(?:\?.*)?$~', $destination);
    if ($valid && $message !== '') {
        $parts = parse_url($destination); parse_str($parts['query'] ?? '', $query); $query['text'] = $message;
        $destination = 'https://'.$parts['host'].($parts['path'] ?? '').'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
@endphp
@if($enabled && $valid)
<div x-data="{ expanded: false }" class="fixed bottom-5 right-4 z-40 flex items-center gap-2">
    <button type="button" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="whatsapp-contact" aria-label="{{ $label }}" class="rounded-full bg-emerald-700 p-3 text-white shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 sm:hidden">WhatsApp</button>
    <a id="whatsapp-contact" data-whatsapp-cta data-context="{{ $context }}" data-language="{{ $locale }}" href="{{ $destination }}" target="_blank" rel="noopener noreferrer" :class="expanded ? 'inline-flex' : 'hidden sm:inline-flex'" class="items-center gap-2 rounded-full bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-lg hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">{{ $label }} <span aria-hidden="true">↗</span></a>
</div>
@endif
