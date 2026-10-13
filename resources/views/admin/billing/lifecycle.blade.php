@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-5xl space-y-6"><div><a class="text-sm font-semibold text-amber-800" href="{{ route('admin.students.show', $package->student_id) }}">← Student record</a><h1 class="mt-3 font-serif text-3xl font-bold">{{ $package->package_name }} · Purchase #{{ $package->id }}</h1><p class="mt-2 text-sm text-slate-600">{{ $package->student?->name ?? 'Historical student' }} · Final price {{ $package->final_price }} {{ $package->currency }} · Due {{ $summary['balance_due'] }} · Overpaid {{ $summary['overpaid'] }}</p><a class="mt-3 inline-block font-semibold text-amber-800" href="{{ route('admin.statements.show', $package) }}">Statement & receipts →</a></div>
@if($originRenewal)<p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">Renewed on {{ $originRenewal->renewal_date->toDateString() }} from <a class="font-semibold text-amber-800" href="{{ route('admin.packages.lifecycle', $originRenewal->previous_package_id) }}">purchase #{{ $originRenewal->previous_package_id }}</a>.</p>@endif
<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Expected installments</h2><p class="mt-2 text-sm text-slate-600">Forecasts cover the full final price. Actual net payments satisfy them in due-date order. Refunds reopen amounts due; forecast rows never create payments. Due dates use {{ $timezone }}.</p>
@if(count($installments))<div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Due date</th><th class="p-3">Expected</th><th class="p-3">Paid toward forecast</th><th class="p-3">Due</th><th class="p-3">Computed status</th></tr></thead><tbody>@foreach($installments as $row)<tr class="border-t border-slate-100"><td class="p-3">{{ $row['due_date'] }}</td><td class="p-3">{{ $row['expected_amount'] }}</td><td class="p-3">{{ $row['paid'] }}</td><td class="p-3">{{ $row['due'] }}</td><td class="p-3">{{ str_replace('_', ' ', $row['status']) }}</td></tr>@endforeach</tbody></table></div><form method="POST" action="{{ route('admin.packages.installments.void', $package) }}" class="mt-4">@csrf<button class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Void forecast schedule</button></form>
@else
<form method="POST" action="{{ route('admin.packages.installments', $package) }}" class="mt-5 space-y-3" x-data="{ visible: {{ max(2, count(old('installments', []))) }} }">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
    @for($i = 0; $i < 12; $i++)
    <div x-show="visible > {{ $i }}" @if($i >= max(2, count(old('installments', [])))) style="display: none;" @endif class="grid gap-3 sm:grid-cols-2">
        <label class="text-xs font-semibold">Installment {{ $i + 1 }} amount<input type="number" min="0.01" step="0.01" name="installments[{{ $i }}][expected_amount]" value="{{ old('installments.'.$i.'.expected_amount') }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
        <label class="text-xs font-semibold">Due date<input type="date" name="installments[{{ $i }}][due_date]" value="{{ old('installments.'.$i.'.due_date') }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
    </div>
    @endfor
    <button type="button" x-show="visible < 12" @click="visible++" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Add another installment</button>
    <p class="text-xs text-slate-500">Leave unused rows blank. One immutable schedule per purchase; a voided forecast stays in history.</p>
    <button class="min-h-11 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Save expected schedule</button>
</form>
@endif</section>
<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Renew as a new purchase</h2><p class="mt-2 text-sm text-slate-600">The previous purchase and its payments remain unchanged. A listed offering uses current catalog terms. Repeating a custom purchase preserves its price and typed quantity; set a new expiry if needed. No payment is recorded automatically.</p><form method="POST" action="{{ route('admin.packages.renew', $package) }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf<input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}"><label class="text-sm font-semibold">Renewal date<input type="date" name="renewal_date" required class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label><label class="text-sm font-semibold">Offering<select name="offering_key" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-3"><option value="">Repeat current offering / custom terms</option>@foreach(\App\Domains\Students\Services\StudentLedgerService::PRESETS as $key => $preset)<option value="{{ $key }}">{{ $preset['name'] }} · {{ $preset['price'] }} USD</option>@endforeach</select></label><label class="text-sm font-semibold">Custom purchase expiry (optional)<input type="date" name="expiration_date" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label><x-operational-reason-select /><label class="text-sm font-semibold sm:col-span-2">Notes (optional)<textarea name="notes" maxlength="1000" rows="2" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></textarea></label><button class="min-h-11 justify-self-start rounded-xl bg-amber-700 px-5 py-3 text-sm font-semibold text-white">Create renewal purchase</button></form></section>
<section class="rounded-2xl border border-slate-200 bg-white p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold">Package Status & Freeze / Pause</h2>
            <p class="mt-1 text-sm text-slate-600">Freezing a package temporarily stops lesson booking and extends expiry when resumed.</p>
        </div>
        @if($package->isPaused())
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Paused since {{ $package->paused_at->toDateString() }}</span>
        @else
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Active</span>
        @endif
    </div>

    @if($package->isPaused())
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Package is currently frozen.</strong>
            @if($package->pause_reason) <p class="mt-1 text-xs">Reason: {{ $package->pause_reason }}</p> @endif
            <p class="mt-2 text-xs text-amber-700">Resuming will automatically extend the package expiry deadline by the number of days it was paused.</p>
            <form method="POST" action="{{ route('admin.packages.resume', $package) }}" class="mt-3">
                @csrf
                <button type="submit" class="rounded-xl bg-amber-700 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-800">Resume Package</button>
            </form>
        </div>
    @else
        <div class="mt-4">
            <details class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                <summary class="cursor-pointer text-sm font-semibold text-amber-800 hover:text-amber-950">Freeze / Pause this package</summary>
                <form method="POST" action="{{ route('admin.packages.pause', $package) }}" class="mt-3 space-y-3">
                    @csrf
                    <p class="text-xs text-slate-600">Note: All upcoming lessons funded by this package must be cancelled before freezing.</p>
                    <label class="block text-xs font-semibold text-slate-700">Pause Reason *
                        <input name="reason" required placeholder="e.g. Student travel / medical leave" class="mt-1 block w-full rounded-xl border border-slate-300 p-2.5 text-sm bg-white">
                    </label>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Confirm Pause</button>
                </form>
            </details>
        </div>
    @endif
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="text-xl font-bold">Business & Agreement Notes</h2>
    <p class="mt-1 text-sm text-slate-600">Internal administrative record regarding pricing agreements, discounts, or special conditions.</p>
    <form method="POST" action="{{ route('admin.packages.notes', $package) }}" class="mt-4 space-y-3">
        @csrf
        <textarea name="admin_notes" rows="3" placeholder="Enter private business notes..." class="w-full rounded-xl border border-slate-300 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">{{ old('admin_notes', $package->admin_notes) }}</textarea>
        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Save Business Notes</button>
    </form>
</section>

@if(auth('web')->user()->isSuperAdmin() && $siblingPackages->isNotEmpty() && $summary['remaining_credits'] > 0)
<section class="rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="text-xl font-bold">Transfer Credits to Another Package</h2>
    <p class="mt-1 text-sm text-slate-600">Move unused credits from this package into another package belonging to this student. The transfer is recorded in the append-only ledger.</p>
    <form method="POST" action="{{ route('admin.packages.transfer', $package) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div>
            <label class="block text-xs font-semibold text-slate-700">Target Package *</label>
            <select name="target_package_id" required class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm">
                @foreach($siblingPackages as $sibling)
                    <option value="{{ $sibling->id }}">#{{ $sibling->id }} · {{ $sibling->package_name }} ({{ $sibling->currency }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-700">Number of Credits * (Max: {{ $summary['remaining_credits'] }})</label>
            <input type="number" name="credits" min="1" max="{{ $summary['remaining_credits'] }}" value="1" required class="mt-1 block w-full rounded-xl border border-slate-300 p-2.5 text-sm bg-white">
        </div>
        <div>
            <label for="transfer-source-credits" class="block text-xs font-semibold text-slate-700">Source Lesson Credits *</label>
            <select id="transfer-source-credits" name="source_allocation_id" required class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm">
                @foreach($summary['entitlements'] as $balance)
                    @if($balance['id'] !== null && $balance['available'] > 0)
                        <option value="{{ $balance['id'] }}">{{ $balance['label'] }} · {{ $balance['available'] }} available</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div>
            <label for="transfer-target-credits" class="block text-xs font-semibold text-slate-700">Target Lesson Credits *</label>
            <select id="transfer-target-credits" name="target_allocation_id" required class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm">
                @foreach($siblingPackages as $sibling)
                    <optgroup label="#{{ $sibling->id }} · {{ $sibling->package_name }}">
                        @foreach($sibling->entitlements as $allocation)
                            @if($allocation->type->active)
                                <option value="{{ $allocation->id }}">#{{ $sibling->id }} · {{ $allocation->type->label }}</option>
                            @endif
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Choose credits belonging to the selected target package and matching the source lesson type.</p>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-700">Reason / Description *</label>
            <input name="description" required maxlength="200" placeholder="e.g. Transferred unused credits to upgrade package" class="mt-1 block w-full rounded-xl border border-slate-300 p-2.5 text-sm bg-white">
        </div>
        <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="rounded-xl bg-amber-700 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-800">Transfer Credits</button>
        </div>
    </form>
</section>
@endif

<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="mb-4 text-xl font-bold">Renewal history from this purchase</h2>@forelse($renewals as $renewal)<p class="border-t border-slate-100 py-3 text-sm">{{ $renewal->renewal_date->toDateString() }} · <a class="font-semibold text-amber-800" href="{{ route('admin.packages.lifecycle', $renewal->new_package_id) }}">New purchase #{{ $renewal->new_package_id }} · {{ $renewal->newPackage->package_name }}</a> · {{ str_replace('_', ' ', $renewal->reason_code ?? 'No reason recorded') }}@if($renewal->notes)<span class="mt-2 block whitespace-pre-wrap text-slate-600">{{ $renewal->notes }}</span>@endif</p>@empty<p class="text-sm text-slate-500">No renewals recorded.</p>@endforelse{{ $renewals->links() }}</section></div>
@endsection
