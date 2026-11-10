@props(['item', 'compact' => false])
<a {{ $attributes->class(['student-nav-link'])->merge(['href' => route($item['destination'])]) }}
    @if($item['current']) aria-current="{{ $item['exact'] ? 'page' : 'location' }}" @endif
    @if($compact && $item['mobile'] !== $item['label']) aria-label="{{ __($item['label']) }}" @endif>
    <x-student-portal-icon :name="$item['icon']" />
    <span>{{ __($compact ? $item['mobile'] : $item['label']) }}</span>
</a>
