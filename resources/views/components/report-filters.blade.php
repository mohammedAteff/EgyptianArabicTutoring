@props(['filters', 'fields', 'action', 'exportRoute' => null, 'fixed' => []])
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
<form method="GET" action="{{ $action }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach($fixed as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
    @foreach($fields as $name => $definition)
    <label class="text-xs font-semibold text-slate-600">{{ $definition[0] }}
        @if(is_array($definition[1]))
        <select name="{{ $name }}" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm"><option value="">All</option>@foreach($definition[1] as $value => $label)<option value="{{ $value }}" @selected(($filters[$name] ?? '') == $value)>{{ $label }}</option>@endforeach</select>
        @else
        <input name="{{ $name }}" type="{{ $definition[1] }}" value="{{ $filters[$name] ?? '' }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-2.5 text-sm">
        @endif
    </label>
    @endforeach
    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4"><button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Apply filters</button><a href="{{ $action }}" class="px-3 py-2 text-sm text-slate-600">Clear / all dates</a>
    @if($exportRoute && auth('web')->user()?->isAdmin()) @foreach(['csv'=>'CSV','xlsx'=>'XLSX'] as $format => $label)<a href="{{ route($exportRoute, array_merge($filters, $fixed, ['format' => $format])) }}" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">Export {{ $label }}</a>@endforeach @endif
    </div>
</form>
</div>
