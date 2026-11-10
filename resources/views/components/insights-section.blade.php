@props(['title', 'id', 'collapsible' => false, 'panel' => true, 'open' => false])
@if($collapsible)
<details {{ $attributes->class(['insights-panel insights-disclosure']) }} @if($open) open @endif>
    <summary><h2 id="{{ $id }}" class="text-xl font-semibold">{{ $title }}</h2><x-student-portal-icon name="chevron" /></summary>
    <div class="insights-disclosure-content space-y-4">{{ $slot }}</div>
</details>
@else
<section {{ $attributes->class(['space-y-4', 'insights-panel' => $panel]) }} aria-labelledby="{{ $id }}">
    <h2 id="{{ $id }}" class="text-xl font-semibold">{{ $title }}</h2>
    {{ $slot }}
</section>
@endif
