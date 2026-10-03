@props(['packages'])
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="font-serif text-xl font-bold text-slate-900">Credits &amp; Package Expiration</h2>
    <p class="mt-1 text-sm text-slate-500">Effective validity and credit balances from the session ledger. Courtesy credits share their package's expiry.</p>
    <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[50rem] text-left text-sm">
        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500"><tr>@foreach(['Source / package', 'Acquired', 'Allocated', 'Courtesy', 'Consumed', 'Restored', 'Remaining', 'Effective expiry', 'Status'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse($packages as $item)
        @php($package = $item['package']) @php($summary = $item['summary'])
        <tr><td class="p-3 font-semibold">{{ $package->package_name }}<span class="block text-xs font-normal text-slate-500">Package grant / courtesy adjustments</span></td>
            <td class="p-3">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($package->created_at) }}</td>
            @foreach(['allocated_credits','courtesy_credits','consumed_credits','restored_credits','remaining_credits'] as $metric)<td class="p-3">{{ $summary[$metric] }}</td>@endforeach
            <td class="p-3">{{ $package->expiration_date?->toDateString() ?? 'No expiry' }}</td><td class="p-3">{{ $package->status === 'active' && $package->expiration_date && $package->expiration_date->toDateString() < now(app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone())->toDateString() ? 'Expired' : ($package->status === 'active' && $summary['remaining_credits'] <= 0 ? 'Exhausted' : ucfirst($package->status)) }}</td></tr>
        @empty<tr><td colspan="9" class="p-6 text-center text-slate-500">No packages or credits allocated.</td></tr>@endforelse</tbody>
    </table></div>
</section>
