@props(['isFallback' => false])
@if($isFallback ?? false)
    <p role="status" class="mx-auto my-6 max-w-5xl rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ __('content_fallback_banner') }}
    </p>
@endif
