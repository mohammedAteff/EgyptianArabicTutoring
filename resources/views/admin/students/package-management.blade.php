<div id="package-management" class="mt-5 space-y-5 scroll-mt-24">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold text-slate-900">Package overview</h3>
        <a href="{{ route('admin.billing.cashier', ['student_id' => $student->id]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-amber-600">Open Full Cashier View →</a>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200" tabindex="0" role="region" aria-label="Package overview table">
        <table class="w-full min-w-[80rem] text-left text-xs">
            <caption class="sr-only">All packages for {{ $student->name }}, with their independent financial and credit balances</caption>
            <thead class="bg-slate-50 text-slate-600"><tr>@foreach(['Package / purchase', 'Acquired', 'Original', 'Final', 'Paid', 'Refunded', 'Due', 'Overpayment', 'Credits', 'Effective expiry', 'Status', ''] as $heading)<th scope="col" class="p-3">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($financialPackages as $financial)
                    @php($listedPackage = $financial['package'])
                    <tr class="{{ $selectedFinancial && $selectedFinancial['package']->id === $listedPackage->id ? 'bg-amber-50' : '' }}">
                        <th scope="row" class="p-3 font-semibold text-slate-900">{{ $listedPackage->package_name }}<span class="block font-normal text-slate-500">Purchase #{{ $listedPackage->id }} · {{ $listedPackage->currency }}</span></th>
                        <td class="p-3">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($listedPackage->created_at, $businessTz) }}</td>
                        <td class="p-3">{{ $listedPackage->original_price }}</td><td class="p-3">{{ $listedPackage->final_price }}</td>
                        @foreach(['gross_paid', 'gross_refunded', 'balance_due', 'overpaid', 'balance_text'] as $metric)<td class="p-3">{{ $financial['summary'][$metric] }}</td>@endforeach
                        <td class="p-3">{{ $listedPackage->expiration_date?->toDateString() ?? 'No expiry' }}</td><td class="p-3">{{ $financial['status'] }}</td>
                        <td class="p-3"><a href="{{ route('admin.students.show', ['student' => $student->id, 'package_id' => $listedPackage->id, 'billing_tab' => 'overview']) }}#package-management" aria-label="Manage {{ $listedPackage->package_name }}, purchase {{ $listedPackage->id }}" class="inline-flex min-h-11 items-center rounded-lg px-3 font-semibold text-amber-800 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-amber-600">Manage →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="p-6 text-center text-slate-500">No packages yet. Create one to record purchased sessions and payments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($selectedFinancial)
        @php($package = $selectedFinancial['package'])
        @php($summary = $selectedFinancial['summary'])
        <article data-package-panel="{{ $package->id }}" class="overflow-hidden rounded-xl border border-slate-200">
            <div class="space-y-4 bg-slate-50 p-4">
                <form method="GET" action="{{ route('admin.students.show', $student->id) }}#package-management" class="flex flex-wrap items-end gap-3">
                    <label for="managed-package" class="w-full min-w-0 text-sm font-semibold text-slate-700 sm:w-auto sm:flex-1">Manage one package
                        <select id="managed-package" name="package_id" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-3 text-sm">
                            @foreach($financialPackages as $financial)<option value="{{ $financial['package']->id }}" @selected($package->id === $financial['package']->id)>{{ $financial['package']->package_name }} · Purchase #{{ $financial['package']->id }}</option>@endforeach
                        </select>
                    </label>
                    <input type="hidden" name="billing_tab" value="{{ $billingTab }}">
                    <button type="submit" class="min-h-11 rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-amber-600">Manage Package</button>
                </form>
                <div><h3 class="font-semibold text-slate-900">{{ $package->package_name }} <span class="text-sm font-normal text-slate-500">· Purchase #{{ $package->id }}</span></h3><p class="mt-1 text-xs text-slate-500">{{ $package->currency }} · {{ $selectedFinancial['status'] }} · {{ $summary['balance_text'] }}</p></div>
                <nav aria-label="Selected package sections" class="flex flex-wrap gap-2">
                    @foreach(['overview' => 'Overview', 'payments' => 'Payments', 'credits' => 'Credits', 'expiration' => 'Expiration', 'history' => 'History'] as $tab => $label)
                        <a href="{{ route('admin.students.show', ['student' => $student->id, 'package_id' => $package->id, 'billing_tab' => $tab]) }}#package-management" @if($billingTab === $tab) aria-current="page" @endif class="inline-flex min-h-11 items-center rounded-lg border px-3 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-amber-600 {{ $billingTab === $tab ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="space-y-5 p-4 sm:p-5">
                @if($billingTab === 'overview')
                    <h4 class="font-semibold text-slate-900">Package details</h4>
                    <dl class="grid grid-cols-2 gap-4 text-sm lg:grid-cols-4">
                        <div><dt class="text-slate-500">Acquired</dt><dd class="mt-1 font-semibold">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($package->created_at, $businessTz) }}</dd></div>
                        @foreach(['Original price' => $package->original_price, 'Discount' => $package->discount_amount, 'Final price' => $package->final_price, 'Paid' => $summary['gross_paid'], 'Refunded' => $summary['gross_refunded'], 'Net paid' => $summary['net_paid'], 'Remaining due' => $summary['balance_due'], 'Overpayment' => $summary['overpaid']] as $label => $amount)<div><dt class="text-slate-500">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ $package->currency }} {{ $amount }}</dd></div>@endforeach
                        <div class="col-span-2"><x-entitlement-balances :rows="$summary['entitlements']" /></div>
                        <div><dt class="text-slate-500">Effective expiry</dt><dd class="mt-1 font-semibold">{{ $package->expiration_date?->toDateString() ?? 'No expiry' }}</dd></div>
                        <div><dt class="text-slate-500">Status</dt><dd class="mt-1 font-semibold">{{ $selectedFinancial['status'] }}</dd></div>
                    </dl>
                @elseif($billingTab === 'payments')
                    <form method="POST" action="{{ route('admin.students.payments.store', [$student->id, $package->id]) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <h4 class="font-semibold text-slate-900 sm:col-span-2">Record payment</h4>
                        <label class="text-sm text-slate-700">Amount ({{ $package->currency }})<input name="amount_paid" required inputmode="decimal" value="{{ old('amount_paid') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm text-slate-700">Payment method<select name="payment_method" required class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">@foreach($paymentMethods as $method)<option value="{{ $method->id }}" @selected(old('payment_method', $method->is_default ? $method->id : '') == $method->id)>{{ $method->name }}</option>@endforeach</select></label>
                        <label class="text-sm text-slate-700 sm:col-span-2">External / manual reference<input name="transaction_reference" maxlength="255" value="{{ old('transaction_reference') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm text-slate-700 sm:col-span-2">Private staff note<textarea name="notes" rows="2" maxlength="4000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('notes') }}</textarea></label>
                        <input type="hidden" name="payment_idempotency_key_{{ $package->id }}" value="{{ old('payment_idempotency_key_'.$package->id, (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="sm:col-span-2"><button class="min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Save payment</button></div>
                    </form>
                    <div class="space-y-3 border-t border-slate-200 pt-5">
                        <h4 class="font-semibold text-slate-900">Payments &amp; manual refunds</h4>
                        @forelse($selectedFinancial['payments'] as $item)
                            @php($payment = $item['payment'])
                            <details class="rounded-lg border border-slate-200 p-3">
                                <summary class="cursor-pointer text-sm font-semibold leading-6">Payment #{{ $payment->id }} · {{ $payment->currency }} {{ $payment->amount_paid }} · {{ $payment->payment_method }}<span class="block text-xs font-normal text-slate-500">{{ $item['refundStatus'] }} · Refundable {{ $payment->currency }} {{ $item['refundable'] }} · {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($payment->paid_at, $businessTz) }}</span></summary>
                                @if($payment->transaction_reference)<p class="mt-3 break-words text-sm text-slate-600">Reference: {{ $payment->transaction_reference }}</p>@endif
                                @if($payment->notes)<p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600">Private staff note: {{ $payment->notes }}</p>@endif
                                @if($item['canRefund'])
                                    <form method="POST" action="{{ route('admin.students.refunds.store', [$student->id, $payment->id]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                        @csrf
                                        <label class="text-sm">Refund amount ({{ $payment->currency }})<input name="amount_refunded" required inputmode="decimal" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                                        <label class="text-sm">Reason<input name="reason" maxlength="4000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                                        <input type="hidden" name="refund_idempotency_key_{{ $payment->id }}" value="{{ old('refund_idempotency_key_'.$payment->id, (string) \Illuminate\Support\Str::uuid()) }}">
                                        <div class="sm:col-span-2"><button class="min-h-11 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm font-semibold text-rose-800 hover:bg-rose-100">Record refund</button></div>
                                    </form>
                                @endif
                            </details>
                        @empty<p class="text-sm text-slate-500">No payments recorded for this package.</p>@endforelse
                        <p class="text-xs text-slate-500">Refund records and reasons are available in History → Refunds. Refunds do not restore credits.</p>
                    </div>
                @elseif($billingTab === 'credits')
                    <h4 class="font-semibold text-slate-900">Credit balances</h4>
                    <x-entitlement-balances :rows="$summary['entitlements']" />
                    <p class="text-sm text-slate-500">Courtesy credits share this package's expiry. View individual ledger entries in History → Credit changes.</p>
                    <form method="POST" action="{{ route('admin.students.credits.adjust', [$student->id, $package->id]) }}" class="grid gap-3 border-t border-slate-200 pt-5 sm:grid-cols-2">
                        @csrf
                        <h4 class="font-semibold text-slate-900 sm:col-span-2">Adjust credits</h4>
                        <x-entitlement-allocation-select :package="$package" />
                        <label class="text-sm">Signed credit change<input name="credit_change" required type="number" min="-500" max="500" value="{{ old('credit_change') }}" placeholder="+ / − credits" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm">Reason<input name="description" required maxlength="255" value="{{ old('description') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <input type="hidden" name="credit_idempotency_key_{{ $package->id }}" value="{{ old('credit_idempotency_key_'.$package->id, (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="sm:col-span-2"><button class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Record adjustment</button></div>
                    </form>
                @elseif($billingTab === 'expiration')
                    <h4 class="font-semibold text-slate-900">Effective expiry: {{ $package->expiration_date?->toDateString() ?? 'No expiry' }}</h4>
                    <form method="POST" action="{{ route('admin.students.packages.validity', [$student->id, $package->id]) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="previous_expiration_date" value="{{ $package->expiration_date?->toDateString() }}">
                        <label class="text-sm">Extend expiration<input type="date" name="expiration_date" required min="{{ $package->expiration_date?->copy()->addDay()->toDateString() }}" value="{{ old('expiration_date') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm">Reason<input name="reason" required maxlength="1000" value="{{ old('reason') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <div class="sm:col-span-2"><button class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Extend validity</button></div>
                    </form>
                    <div class="space-y-2 border-t border-slate-200 pt-5"><h4 class="font-semibold text-slate-900">Validity history</h4>
                        @forelse($selectedFinancial['validityHistory'] as $change)<p class="break-words text-sm text-slate-600">Validity extended: {{ $change->previous_data['expiration_date'] ?? 'None' }} → {{ $change->new_data['expiration_date'] ?? 'None' }} · {{ $change->new_data['reason'] ?? '' }} · Staff #{{ $change->administrator_id }} · {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($change->created_at, $businessTz) }}</p>@empty<p class="text-sm text-slate-500">No validity changes recorded.</p>@endforelse
                    </div>
                @elseif($billingTab === 'history')
                    <form method="GET" action="{{ route('admin.students.show', $student->id) }}#package-management" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="package_id" value="{{ $package->id }}"><input type="hidden" name="billing_tab" value="history">
                        <label class="w-full min-w-0 text-sm font-semibold sm:w-auto sm:flex-1">History type<select name="history_type" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">@foreach(['all' => 'All', 'payments' => 'Payments', 'refunds' => 'Refunds', 'credits' => 'Credit changes', 'expiry' => 'Expiry changes'] as $type => $label)<option value="{{ $type }}" @selected($historyType === $type)>{{ $label }}</option>@endforeach</select></label>
                        <button class="min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Apply History Filter</button>
                    </form>
                    <div data-package-history="{{ $package->id }}" class="space-y-3">
                        @forelse($packageHistory as $row)
                            <details class="rounded-lg border border-slate-200 p-3"><summary class="cursor-pointer text-sm font-semibold leading-6">{{ $row['summary'] }}<span class="block text-xs font-normal text-slate-500">{{ $row['reference'] }} · {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($row['at'], $businessTz) }}</span></summary>
                                <dl class="mt-3 space-y-2 text-sm">@foreach($row['details'] as $label => $value)@if($value !== null && $value !== '')<div class="break-words"><dt class="font-semibold text-slate-700">{{ $label }}</dt><dd class="whitespace-pre-wrap text-slate-600">{{ $value }}</dd></div>@endif@endforeach</dl>
                            </details>
                        @empty<p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">No matching history for this package.</p>@endforelse
                    </div>
                @endif
            </div>
        </article>
    @endif
</div>
