@props(['field' => null, 'all' => false, 'bin' => false, 'label' => 'Copy'])
<button type="button" @if($field) data-copy-field="{{ $field }}" @elseif($all) data-copy-student @elseif($bin) data-copy-bin @endif
    title="{{ $label }}" aria-label="{{ $label }}" class="inline-flex min-h-8 min-w-8 items-center justify-center gap-1 rounded-lg px-1.5 text-slate-500 hover:bg-slate-100 hover:text-amber-800 focus-visible:outline-2 focus-visible:outline-amber-600">
    <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg>
    @if($all)<span class="text-sm font-semibold">Copy Student Details</span>@endif
    <span data-copy-status aria-live="polite" class="text-xs"></span>
</button>
