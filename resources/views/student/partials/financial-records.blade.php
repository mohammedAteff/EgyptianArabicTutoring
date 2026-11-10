@php
    $paymentRows = ($receipt ? collect([$receipt]) : $package->payments)->map(fn ($payment) => [
        'reference' => $references['payments'][$payment->id],
        'url' => route('student.receipts.show', ['package' => $package, 'payment' => $payment->id]),
        'date' => $payment->paid_at->copy()->setTimezone($timezone)->locale(app()->getLocale())->translatedFormat(localized_date_format('j M Y, g:i A')),
        'amount' => $payment->amount_paid,
        'method' => $payment->payment_method ?: __('Manual payment'),
        'external_reference' => $payment->transaction_reference ?: __('Not recorded'),
    ]);
    $refundRows = $receipt ? $package->refunds->where('payment_record_id', $receipt->id) : $package->refunds;
@endphp
<section class="student-surface student-financial-records" aria-labelledby="recorded-payments">
    <h2 id="recorded-payments">{{ __($receipt ? 'Payment Recorded' : 'Payments Recorded') }}</h2>
    <p class="mt-2 text-sm text-stone-600">{{ __('These are the payments recorded for your package. A reference is shown when one has been recorded.') }}</p>
    <div class="student-financial-stacked mt-4">
        @forelse($paymentRows as $payment)
            <article class="student-financial-record">
                <h3><a href="{{ $payment['url'] }}" class="student-record-link">{{ __('Payment #') }}{{ $payment['reference'] }}</a></h3>
                <dl>
                    <div><dt>{{ __('Recorded date / time') }}</dt><dd>{{ $payment['date'] }}</dd></div>
                    <div><dt>{{ __('Amount') }}</dt><dd class="student-record-amount"><x-student-money :amount="$payment['amount']" :currency="$package->currency" /></dd></div>
                    <div><dt>{{ __('Recorded method') }}</dt><dd>{{ $payment['method'] }}</dd></div>
                    <div><dt>{{ __('Recorded external reference') }}</dt><dd class="student-long-reference">{{ $payment['external_reference'] }}</dd></div>
                </dl>
            </article>
        @empty<p class="text-sm text-stone-500">{{ __('No Payments Recorded Yet.') }}</p>@endforelse
    </div>
    <div class="student-financial-table mt-4">
        <table>
            <caption class="sr-only">{{ __('Recorded payments in') }} {{ $package->currency }}{{ __('; dates shown in') }} {{ $timezone }}</caption>
            <thead><tr><th scope="col">{{ __('Record / date') }}</th><th scope="col">{{ __('Amount') }}</th><th scope="col">{{ __('Recorded method') }}</th><th scope="col">{{ __('Recorded external reference') }}</th></tr></thead>
            <tbody>@forelse($paymentRows as $payment)
                <tr><th scope="row"><a href="{{ $payment['url'] }}" class="student-record-link">{{ __('Payment #') }}{{ $payment['reference'] }}</a><span class="mt-1 block text-xs font-normal text-stone-500">{{ $payment['date'] }}</span></th><td><x-student-money :amount="$payment['amount']" :currency="$package->currency" /></td><td>{{ $payment['method'] }}</td><td class="student-long-reference">{{ $payment['external_reference'] }}</td></tr>
            @empty<tr><td colspan="4">{{ __('No Payments Recorded Yet.') }}</td></tr>@endforelse</tbody>
        </table>
    </div>
    <h3 class="mb-3 mt-6">{{ __('Refunds Recorded') }}{{ $receipt ? __(' against this payment') : '' }}</h3>
    @forelse($refundRows as $refund)
        <article class="student-financial-record">
            <h4 class="font-semibold">{{ __('Refund #') }}{{ $references['refunds'][$refund->id] }}</h4>
            <dl class="student-refund-details">
                <div><dt>{{ __('Original payment') }}</dt><dd><a class="student-record-link" href="{{ route('student.receipts.show', ['package' => $package, 'payment' => $refund->payment_record_id]) }}">{{ __('Original payment #') }}{{ $references['payments'][$refund->payment_record_id] }}</a></dd></div>
                <div><dt>{{ __('Amount refunded') }}</dt><dd><x-student-money :amount="$refund->amount_refunded" :currency="$package->currency" /></dd></div>
                <div><dt>{{ __('Recorded date / time') }}</dt><dd>{{ localized_date($refund->refunded_at->copy()->setTimezone($timezone), 'j M Y, g:i A') }}</dd></div>
            </dl>
        </article>
    @empty<p class="text-sm text-stone-500">{{ __('No Refunds Recorded.') }}</p>@endforelse
</section>
@if(! $receipt && count($installments))
    <section @class(['student-surface', 'student-financial-records', 'student-short-forecast' => count($installments) <= 3]) aria-labelledby="expected-installments">
        <h2 id="expected-installments">{{ __('Expected Installments') }}</h2>
        <p class="mt-2 text-sm text-stone-600">{{ __('These are expected installments. They are separate from payments already recorded.') }}</p>
        <div class="student-financial-stacked mt-4">
            @foreach($installments as $row)
                <article class="student-financial-record"><h3>{{ __('Due') }} {{ $row['due_date'] }}</h3><dl>
                    <div><dt>{{ __('Expected') }}</dt><dd><x-student-money :amount="$row['expected_amount']" :currency="$package->currency" /></dd></div>
                    <div><dt>{{ __('Outstanding forecast') }}</dt><dd><x-student-money :amount="$row['due']" :currency="$package->currency" /></dd></div>
                    <div><dt>{{ __('Computed status') }}</dt><dd>{{ __(ucfirst(str_replace('_', ' ', $row['status']))) }}</dd></div>
                </dl></article>
            @endforeach
        </div>
        <div class="student-financial-table mt-4"><table>
            <caption class="sr-only">{{ __('Expected installments in') }} {{ $package->currency }} {{ __('— forecast, separate from recorded payments') }}</caption>
            <thead><tr><th scope="col">{{ __('Due date') }}</th><th scope="col">{{ __('Expected') }}</th><th scope="col">{{ __('Outstanding forecast') }}</th><th scope="col">{{ __('Computed status') }}</th></tr></thead>
            <tbody>@foreach($installments as $row)<tr><th scope="row">{{ $row['due_date'] }}</th><td><x-student-money :amount="$row['expected_amount']" :currency="$package->currency" /></td><td><x-student-money :amount="$row['due']" :currency="$package->currency" /></td><td>{{ __(ucfirst(str_replace('_', ' ', $row['status']))) }}</td></tr>@endforeach</tbody>
        </table></div>
    </section>
@endif
