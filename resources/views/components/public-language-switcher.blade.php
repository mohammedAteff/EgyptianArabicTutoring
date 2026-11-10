@props(['availableLocales', 'currentLocale', 'urlService'])
<div class="public-language-switcher">
    <div class="public-language" role="group" aria-label="{{ website_label('Language selector') }}">
        @foreach($availableLocales as $code => $loc)
            @if($currentLocale === $code)
                <span class="is-current" aria-current="true">
                    <span aria-hidden="true">{{ $loc['code'] }}</span><span class="sr-only">{{ $loc['label'] }}</span>
                </span>
            @else
                <a href="{{ url()->query($urlService->getUrlForLocale($code), ['lang' => $code]) }}"
                    @if(request()->routeIs('booking.*'))
                        @click.prevent="if (window.Livewire) { const c = document.querySelector('[wire\\:id]'); if (c) { const comp = window.Livewire.find(c.getAttribute('wire:id')); if (comp) { const pending = {}; const firstNameEl = document.getElementById('first_name'); const lastNameEl = document.getElementById('last_name'); const dobEl = document.getElementById('date_of_birth'); const emailEl = document.getElementById('email'); const phoneEl = document.getElementById('phone'); const notesEl = document.getElementById('notes'); if (firstNameEl) pending.first_name = firstNameEl.value; if (lastNameEl) pending.last_name = lastNameEl.value; if (dobEl) pending.date_of_birth = dobEl.value; if (emailEl) pending.email = emailEl.value; if (phoneEl) pending.phone = phoneEl.value; if (notesEl) pending.notes = notesEl.value; comp.switchLanguage('{{ $code }}', pending); return; } } } window.location.href = $el.href;"
                    @endif
                   class="public-language-link"
                   title="{{ $loc['label'] }}"
                   aria-label="{{ $loc['label'] }}">
                    <span aria-hidden="true">{{ $loc['code'] }}</span><span class="sr-only">{{ $loc['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>
</div>
