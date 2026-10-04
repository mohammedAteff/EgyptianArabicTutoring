@props(['field' => null, 'all' => false, 'bin' => false, 'label' => 'Copy'])
<button type="button" @if($field) data-copy-field="{{ $field }}" @elseif($all) data-copy-student @elseif($bin) data-copy-bin @endif
    title="{{ $label }}" aria-label="{{ $label }}" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1.5 rounded-lg px-2 align-middle text-slate-500 hover:bg-slate-100 hover:text-amber-800 focus-visible:outline-2 focus-visible:outline-amber-600">
    <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><rect x="8" y="8" width="12" height="12" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
    @if($all)<span class="text-sm font-semibold">Copy Student Details</span>@endif
    <span data-copy-status aria-live="polite" class="text-xs"></span>
</button>
