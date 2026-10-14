@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div><h1 class="font-serif text-3xl font-bold text-slate-900">Website Copy & Pages</h1><p class="mt-2 text-sm text-slate-600">One workspace for public wording, FAQs, legal documents and translations. Drafts stay private until you publish.</p></div>
    @include('admin.website-copy.navigation')
    @if(session('success'))<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($tab === 'faqs')
        @include('admin.content.faqs')
    @elseif($tab === 'pages')
        @include('admin.pages.list')
    @else
    <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
        <form method="GET" action="{{ route('admin.website-copy.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="copy"><input type="hidden" name="locale" value="{{ $locale }}">
            <label class="min-w-0 grow text-sm font-semibold">Choose a page or shared content<select name="page" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 p-3">@foreach($pageOptions as $key => $label)<option value="{{ $key }}" @selected($pageKey === $key)>{{ $label }}</option>@endforeach</select></label>
            <button class="min-h-11 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Open editor</button>
        </form>
        <nav class="flex flex-wrap gap-2" aria-label="Content language">@foreach(['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch'] as $key => $name)<a href="{{ route('admin.website-copy.index', ['page' => $pageKey, 'locale' => $key]) }}" @if($locale === $key) aria-current="page" @endif class="rounded-xl border px-4 py-3 text-sm font-semibold {{ $locale === $key ? 'border-amber-500 bg-amber-50 text-amber-900' : 'border-slate-200 text-slate-600' }}">{{ $name }}</a>@endforeach</nav>
        <p class="text-sm text-slate-600">{{ $copy?->published_at ? 'Published '.$copy->published_at->format('d M Y H:i').' · Version '.$copy->version : 'No published override. The current website content remains in use.' }}</p>
        @if($locale !== 'en')<p class="text-sm {{ $translationStale ? 'text-amber-800' : 'text-slate-600' }}">{{ $translationStale ? 'English changed since this translation was published. Review the reference wording before publishing an update.' : 'English is shown beside each field for reference. Review every section before publishing this language.' }}</p>@endif
        @if($selectedRevision)<p role="status" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Reviewing revision #{{ $selectedRevision->revision_number }}. Save it as a new draft to restore its wording without changing the live page.</p>@endif
    </section>
    <form id="website-copy-editor" method="POST" action="{{ route('admin.website-copy.update') }}" x-data="{ search: '', dirty: false }" @input="dirty = true" @submit="dirty = false" @beforeunload.window="if(dirty) { $event.preventDefault(); $event.returnValue = ''; }" class="space-y-5">
        @csrf<input type="hidden" name="page" value="{{ $pageKey }}"><input type="hidden" name="locale" value="{{ $locale }}"><input type="hidden" name="state" value="{{ $state }}">
        <div class="flex flex-wrap items-center gap-3"><label class="grow text-sm font-semibold">Find a field<input type="search" x-model="search" placeholder="Search headings, buttons or paragraphs…" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 p-3"></label><span x-show="dirty" x-cloak class="text-sm font-semibold text-amber-800">Unsaved changes</span></div>
        @foreach(collect($fields)->groupBy('group', true) as $group => $groupFields)
        <details @if($loop->first) open @endif class="rounded-2xl border border-slate-200 bg-white p-5" x-show="!search || $el.textContent.toLowerCase().includes(search.toLowerCase())" x-effect="if(search) $el.open = $el.textContent.toLowerCase().includes(search.toLowerCase())">
            <summary class="cursor-pointer text-lg font-bold text-slate-900">{{ $group }}</summary>
            <div class="mt-5 space-y-6">
            @foreach($groupFields as $key => $field)
                @php $value = old('values.'.$key, $values[$key] ?? $field['default']); @endphp
                <div class="space-y-2">
                @if($field['type'] === 'richtext')
                    <x-lms.rich-text-editor :id="'copy-'.$key" :value="(string) $value" :name="'values['.$key.']'" :label="$field['label']" />
                @elseif($field['type'] === 'checkbox')
                    <input type="hidden" name="values[{{ $key }}]" value="0"><label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input type="checkbox" name="values[{{ $key }}]" value="1" @checked($value)>{{ $field['label'] }}</label>
                @elseif($field['type'] === 'textarea')
                    <label class="block text-sm font-semibold" for="copy-{{ $key }}">{{ $field['label'] }}</label><textarea id="copy-{{ $key }}" name="values[{{ $key }}]" rows="4" maxlength="20000" class="block w-full rounded-xl border border-slate-300 p-3 text-sm">{{ $value }}</textarea>
                @else
                    <label class="block text-sm font-semibold" for="copy-{{ $key }}">{{ $field['label'] }}</label><input id="copy-{{ $key }}" name="values[{{ $key }}]" type="{{ $field['type'] === 'number' ? 'number' : 'text' }}" @if($field['type'] === 'number') min="1" max="100" @endif value="{{ $value }}" maxlength="20000" class="block min-h-11 w-full rounded-xl border border-slate-300 p-3 text-sm">
                @endif
                @if($field['type'] === 'url')<p class="text-xs text-slate-500">Optional destination override. Use HTTPS or a website path such as /booking. Leave blank to keep the current link.</p>@endif
                @if($field['type'] === 'image')
                    @php $portraitPreview = app(\App\Domains\CMS\Services\AboutImageService::class)->url($value); @endphp
                    @if($portraitPreview)<img id="about_image_preview" src="{{ $portraitPreview }}" alt="Current portrait" class="max-h-52 rounded-xl object-cover">@endif
                    <button type="button" onclick="window.openMediaPicker('copy-{{ $key }}', 'about_image_preview', true)" class="min-h-11 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold">Choose image from Media Library</button>
                @endif
                @if(str_starts_with($field['path'], 'counters.'))<p class="text-xs text-slate-500">Keep {count} or {time} where the live number should appear.</p>@endif
                @if($locale !== 'en' && !in_array($field['type'], ['checkbox', 'number', 'image'], true))<p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600"><strong>English:</strong> {{ strip_tags((string) ($englishValues[$key] ?? '')) }}</p>@endif
                @if((string) $value !== (string) ($publishedValues[$key] ?? ''))<details class="text-sm text-slate-500"><summary class="cursor-pointer">Compare with published wording</summary><p class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 p-3">{{ strip_tags((string) ($publishedValues[$key] ?? '')) }}</p></details>@endif
                </div>
            @endforeach
            </div>
        </details>
        @endforeach
        <div class="sticky bottom-0 z-20 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            @if($locale !== 'en')<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="reviewed_source" value="1" class="mt-1">I reviewed this translation against the current English reference.</label>@endif
            <div class="flex flex-wrap gap-3"><a href="{{ route('admin.website-copy.preview', ['page' => $pageKey, 'locale' => $locale]) }}" target="_blank" rel="noopener" class="min-h-11 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold">Preview saved draft</a><button name="action" value="draft" class="min-h-11 rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold">Save as Draft</button><button name="action" value="publish" class="min-h-11 rounded-xl bg-amber-600 px-5 py-3 text-sm font-bold text-white">Publish {{ strtoupper($locale) }} Copy</button></div>
            <p class="text-xs text-slate-500">Save before previewing. Publishing applies this page and language. Revisions remain available below.</p>
        </div>
    </form>
    <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="text-lg font-bold">Revision history</h2><div class="mt-3 space-y-2">@forelse($revisions as $revision)<a class="block text-sm font-semibold text-amber-800" href="{{ route('admin.website-copy.index', ['page' => $pageKey, 'locale' => $locale, 'revision' => $revision->id]) }}">Review / restore revision #{{ $revision->revision_number }} · {{ $revision->status }} · {{ $revision->created_at->format('d M Y H:i') }}</a>@empty<p class="text-sm text-slate-500">Your first saved edit will create a revision.</p>@endforelse</div></section>
    @endif
</div>
@endsection
