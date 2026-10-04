@props(['packages'])

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

    <h2 class="font-serif text-xl font-bold text-slate-900">Credits &amp; Package Expiration</h2>

    <p class="mt-1 text-sm text-slate-500">Effective validity and credit balances from the session ledger. Courtesy credits share their package's expiry.</p>

    <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[50rem] text-left text-sm">

        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500"><tr>@foreach(['Source / package', 'Acquired', 'Entitlements', 'Effective expiry', 'Status'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead>

        <tbody class="divide-y divide-slate-100">@forelse($packages as $item)

        @php($package = $item['package']) @php($summary = $item['summary'])

        <tr><td class="p-3 font-semibold">{{ $package->package_name }}<span class="block text-xs font-normal text-slate-500">Offering {{ $package->offering_key ?? 'Unclassified' }} · Purchase #{{ $package->id }}</span></td>

            <td class="p-3">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($package->created_at) }}</td>

            <td class="p-3"><x-entitlement-balances :rows="$summary['entitlements']" /></td>

            <td class="p-3">{{ $package->expiration_date?->toDateString() ?? 'No expiry' }}</td><td class="p-3">{{ $summary['entitlement_status'] }}</td></tr>

        @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">No packages or credits allocated.</td></tr>@endforelse</tbody>

    </table></div>

</section>
