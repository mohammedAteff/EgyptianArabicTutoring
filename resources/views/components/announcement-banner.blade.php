@props(['announcement' => null])
@if($announcement)
<aside aria-label="{{ __('Site announcement') }}" data-site-announcement
    x-data="announcementBanner(@js($announcement['version']))" x-show="!dismissed"
    @class(['border-b px-4 py-3 text-sm', 'border-sky-200 bg-sky-50 text-sky-950' => $announcement['severity'] === 'information', 'border-amber-300 bg-amber-50 text-amber-950' => $announcement['severity'] === 'warning', 'border-red-300 bg-red-50 text-red-950' => $announcement['severity'] === 'urgent'])>
    <div class="mx-auto flex max-w-7xl items-start justify-between gap-4">
        <div class="min-w-0 flex-1 py-2">
            <span class="mr-2 font-semibold">{{ __(ucfirst($announcement['severity'])) }}</span>
            <span class="whitespace-pre-line break-words">{{ $announcement['message'] }}</span>
            @if($announcement['cta_url'] && $announcement['cta_label'])
                <a href="{{ $announcement['cta_url'] }}" rel="noopener noreferrer" class="ml-3 inline-flex min-h-11 items-center rounded-lg px-2 font-semibold underline underline-offset-4 focus-visible:outline-2">{{ $announcement['cta_label'] }}</a>
            @endif
        </div>
        @if($announcement['dismissible'])
            <button type="button" @click="dismiss()" aria-label="{{ __('Dismiss announcement') }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-black/5 focus-visible:outline-2">
                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
        @endif
    </div>
</aside>
@endif
