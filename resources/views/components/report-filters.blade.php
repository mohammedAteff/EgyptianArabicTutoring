@props(['filters', 'fields', 'action', 'exportRoute' => null, 'fixed' => [], 'allowExport' => null])
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
<h2 class="mb-3 text-sm font-semibold text-slate-900">Filters</h2>
<form method="GET" action="{{ $action }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach($fixed as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
    @foreach($fields as $name => $definition)
    <label class="text-xs font-semibold text-slate-600">{{ $definition[0] }}
        @if(is_array($definition[1]))
        <select name="{{ $name }}" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm"><option value="">{{ in_array($name, ['range', 'version_id', 'sort', 'dir', 'owner', 'population'], true) ? 'Default' : 'All' }}</option>@foreach($definition[1] as $value => $label)<option value="{{ $value }}" @selected(($filters[$name] ?? '') == $value)>{{ $label }}</option>@endforeach</select>
        @else
        <input name="{{ $name }}" type="{{ $definition[1] }}" value="{{ $filters[$name] ?? '' }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-2.5 text-sm">
        @endif
    </label>
    @endforeach
    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4"><button type="submit" class="min-h-11 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">Apply Filters</button><a href="{{ $action }}{{ $fixed ? '?'.http_build_query($fixed) : '' }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">Clear Filters</a>
    @if($exportRoute && ($allowExport ?? auth('web')->user()?->isAdmin())) @foreach(['csv'=>'CSV','xlsx'=>'XLSX'] as $format => $label)<a href="{{ route($exportRoute, array_merge($filters, $fixed, ['format' => $format])) }}" class="inline-flex min-h-11 items-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">Export {{ $label }}</a>@endforeach @endif
    </div>
</form>
@if($exportRoute)<p class="mt-3 text-xs text-slate-500">Exports include all matching rows. XLSX is limited to 10,000 rows / 200,000 cells; use CSV for larger reports.</p>@endif
</div>
