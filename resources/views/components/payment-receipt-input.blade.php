@php($receiptTz = app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone())
<div class="space-y-2">
    <label class="block text-sm font-semibold text-slate-700">Payment received at
        <input name="received_at" type="datetime-local" step="1" required value="{{ old('received_at', now(old('received_at_timezone', $receiptTz))->format('Y-m-d\\TH:i:s')) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
    </label>
    <label class="block text-xs text-slate-600">Receipt timezone
        <select name="received_at_timezone" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
            @foreach(array_unique([$receiptTz, 'UTC']) as $timezone)<option value="{{ $timezone }}" @selected(old('received_at_timezone', $receiptTz) === $timezone)>{{ $timezone }}</option>@endforeach
        </select>
    </label>
    <p class="text-xs leading-5 text-slate-500">Enter when the payment was actually received. The first payment starts the package's validity immediately, whether it pays the full amount or only part of it; earlier dated entitlements are preserved. Later payments do not restart the validity period. For a repeated local clock time, enter the exact receipt in UTC.</p>
</div>
