@props(['booking'])
@php
    $materials = $booking->lessonMaterials->where('kind', '!=', 'recording');
    $recordings = $booking->lessonMaterials->where('kind', 'recording');
@endphp
@if($materials->isNotEmpty() || $recordings->isNotEmpty())
<div {{ $attributes->merge(['class' => 'mt-5 space-y-4 border-t border-stone-200 pt-4']) }}>
    @foreach(['Materials' => $materials, 'Recording' => $recordings] as $heading => $items)
        @if($items->isNotEmpty())
        <section aria-label="{{ $heading }}">
            <h3 class="font-semibold text-nile-900">{{ $heading }}</h3>
            <ul class="mt-2 space-y-2">
                @foreach($items as $material)
                    <li>
                        <a href="{{ route('student.lessons.materials.open', [$booking->id, $material->id]) }}" @if(in_array($material->kind, ['external_link', 'recording', 'resource'], true)) target="_blank" rel="noopener noreferrer" @endif class="inline-flex min-h-11 items-center break-words text-sm font-semibold text-nile-800 underline">{{ $material->title }}</a>
                        @if($material->description)<p class="whitespace-pre-line break-words text-sm text-stone-600">{{ $material->description }}</p>@endif
                    </li>
                @endforeach
            </ul>
        </section>
        @endif
    @endforeach
</div>
@endif
