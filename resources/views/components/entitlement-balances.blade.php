@props(['rows'])
<div class="space-y-3">@forelse($rows as $row)
<div class="rounded-lg border border-slate-200 bg-white p-3"><p class="font-semibold">{{ $row['label'] }}</p><p class="mt-1 text-sm">{{ $row['remaining'] }} remaining · {{ $row['available'] }} available</p><p class="mt-1 text-xs text-slate-500">Allocated {{ $row['allocated'] }} · Courtesy {{ $row['courtesy'] }} · Consumed {{ $row['consumed'] }} · Restored {{ $row['restored'] }} · Forfeited {{ $row['forfeited'] }}@if($row['id']) · Allocation #{{ $row['id'] }}@endif</p></div>
@empty<p class="text-sm text-slate-500">No entitlements allocated.</p>@endforelse</div>
