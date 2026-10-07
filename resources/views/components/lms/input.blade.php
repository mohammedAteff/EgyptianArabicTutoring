@props(['label','name','value'=>null,'type'=>'text','id'=>null,'required'=>false,'hint'=>null])
@php $controlId = $id ?? 'studio-'.str_replace(['[',']','_'],['-','','-'],$name); @endphp
<div>
    <label for="{{ $controlId }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>
    @if($type === 'textarea')
        <textarea id="{{ $controlId }}" name="{{ $name }}" dir="auto" @required($required) {{ $attributes->merge(['class'=>'w-full min-h-24 rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm focus:outline-2 focus:outline-amber-600','rows'=>4]) }}>{{ $value }}</textarea>
    @else
        <input id="{{ $controlId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" dir="auto" @required($required) {{ $attributes->merge(['class'=>'min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm focus:outline-2 focus:outline-amber-600']) }}>
    @endif
    @if($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
</div>
