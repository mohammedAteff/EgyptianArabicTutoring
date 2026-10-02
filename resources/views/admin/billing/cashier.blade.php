@extends('layouts.admin')

@section('content')
<div class="space-y-8" x-data="{
    selectedStudentId: {{ $selectedStudent ? $selectedStudent->id : 'null' }},
    selectedStudentName: '{{ $selectedStudent ? addslashes($selectedStudent->name) : '' }}',
    hasDiagnosticCredit: {{ $diagnosticEligibility && $diagnosticEligibility['eligible'] ? 'true' : 'false' }},
    diagnosticAmount: '25.00',
    applyDiagnosticCredit: {{ $diagnosticEligibility && $diagnosticEligibility['eligible'] ? 'true' : 'false' }},
    selectedPreset: null,
    packageName: '',
    sessionCount: 1,
    originalPrice: '0.00',
    discountAmount: '0.00',
    finalPrice: '0.00',
    validityDays: 30,
    expirationDate: '',
    currency: 'USD',
    idempotencyKey: '',
    paymentPackageId: null,
    paymentAmount: '',
    paymentMethod: 'PayPal - Manual',
    paymentRef: '',
    paymentIdempotency: '',
    refundPaymentId: null,
    refundPackageId: null,
    refundAmount: '',
    refundReason: '',
    forfeitCredits: 0,
    refundIdempotency: '',
    courtesyPackageId: null,
    courtesyCredits: 1,
    courtesyReason: '',
    courtesyIdempotency: '',
    init() {
        this.generateIdempotency('package');
        this.generateIdempotency('payment');
        this.generateIdempotency('refund');
        this.generateIdempotency('courtesy');
    },
    generateIdempotency(type) {
        const key = window.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
            const random = Math.floor(Math.random() * 16);
            return (character === 'x' ? random : (random & 3) | 8).toString(16);
        });
        if (type === 'package') this.idempotencyKey = key;
        if (type === 'payment') this.paymentIdempotency = key;
        if (type === 'refund') this.refundIdempotency = key;
        if (type === 'courtesy') this.courtesyIdempotency = key;
    },
    selectPreset(preset) {
        this.selectedPreset = preset.key;
        this.packageName = preset.name;
        this.sessionCount = preset.sessions;
        this.originalPrice = preset.price;
        this.validityDays = preset.validity_days;

        if (this.hasDiagnosticCredit && (preset.key === 'foundation_track' || preset.key === 'fluency_track') && this.applyDiagnosticCredit) {
            this.discountAmount = '25.00';
            this.finalPrice = (parseFloat(preset.price) - 25.00).toFixed(2);
        } else {
            this.discountAmount = '0.00';
            this.finalPrice = parseFloat(preset.price).toFixed(2);
        }

        // Calculate expiration date (Cairo local + validity days)
        const d = new Date();
        d.setDate(d.getDate() + preset.validity_days);
        this.expirationDate = d.toISOString().split('T')[0];
    },
    toggleDiagnosticCredit() {
        if (!this.selectedPreset) return;
        if (this.applyDiagnosticCredit && this.hasDiagnosticCredit) {
            this.discountAmount = '25.00';
            this.finalPrice = (parseFloat(this.originalPrice) - 25.00).toFixed(2);
        } else {
            this.discountAmount = '0.00';
            this.finalPrice = parseFloat(this.originalPrice).toFixed(2);
        }
    },
    recalculateCustom() {
        const orig = parseFloat(this.originalPrice) || 0;
        const disc = parseFloat(this.discountAmount) || 0;
        this.finalPrice = Math.max(0, orig - disc).toFixed(2);
    }
}">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Cashier Operations Hub</h1>
            <p class="text-sm text-slate-500 mt-1">Manual payment recording, installment tracking, canonical package pricing, and diagnostic credit settlement.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.billing.reconcile') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Reconcile Diagnostic</span>
            </a>
            <a href="{{ route('admin.billing.export', ['format' => 'csv']) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-xl text-xs font-semibold shadow-xs transition-colors">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export CSV</span>
            </a>
            <a href="{{ route('admin.billing.export', ['format' => 'xlsx']) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-semibold shadow-xs transition-colors">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export XLSX</span>
            </a>
        </div>
    </div>

    <!-- Student Selector & Search Filter -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <h2 class="text-lg font-bold font-serif text-slate-900">Select Student</h2>
            <form action="{{ route('admin.billing.cashier') }}" method="GET" class="w-full sm:w-80">
                <div class="relative">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search by student name, email, or phone..."
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </form>
        </div>

        @if($selectedStudent)
            <div class="p-4 bg-amber-50 rounded-2xl border border-amber-200 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-600 text-white font-bold flex items-center justify-center text-sm shadow-xs">
                        {{ strtoupper(substr($selectedStudent->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 text-sm">{{ $selectedStudent->name }}</div>
                        <div class="text-xs text-slate-500">{{ $selectedStudent->email }} • {{ $selectedStudent->phone ?? 'No phone' }}</div>
                    </div>
                </div>

                @if($diagnosticEligibility && $diagnosticEligibility['eligible'])
                    <div class="px-3.5 py-1.5 bg-emerald-100 text-emerald-900 border border-emerald-300 rounded-xl text-xs font-semibold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>48-Hour Diagnostic Credit Eligible (-$25.00)</span>
                    </div>
                @endif

                <a href="{{ route('admin.billing.cashier') }}" class="text-xs text-slate-400 hover:text-slate-600 underline">Change Student</a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($students as $st)
                    <a href="{{ route('admin.billing.cashier', ['student_id' => $st->id, 'q' => $search]) }}"
                       class="p-3 bg-slate-50 hover:bg-amber-50/60 rounded-xl border border-slate-200 transition-colors flex items-center justify-between">
                        <div class="truncate pr-2">
                            <div class="font-bold text-slate-800 text-xs truncate">{{ $st->name }}</div>
                            <div class="text-[11px] text-slate-400 truncate">{{ $st->email }}</div>
                        </div>
                        <span class="text-xs font-semibold text-amber-600 shrink-0">Select &rarr;</span>
                    </a>
                @endforeach
            </div>
            <div class="pt-2">
                {{ $students->links() }}
            </div>
        @endif
    </div>

    <!-- Canonical Presets Quick-Enroll Section -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        <div>
            <h2 class="text-lg font-bold font-serif text-slate-900">Canonical Pricing Presets (Abdallah Specification)</h2>
            <p class="text-xs text-slate-500 mt-1">One-click standard coaching tiers with preset durations, standard rates, and Cairo midnight validity windows.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($presets as $pKey => $preset)
                <button type="button" @click="selectPreset({{ json_encode($preset) }})"
                        :class="selectedPreset === '{{ $pKey }}' ? 'border-amber-500 ring-2 ring-amber-500/20 bg-amber-50/40' : 'border-slate-200 bg-slate-50 hover:border-slate-300'"
                        class="p-4 rounded-2xl border text-left transition-all relative flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full {{ $pKey === 'diagnostic_roadmap' ? 'bg-blue-100 text-blue-800' : ($pKey === 'fluency_track' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800') }}">
                                {{ $preset['audience'] }}
                            </span>
                            <span class="font-mono font-bold text-slate-900">${{ $preset['price'] }}</span>
                        </div>
                        <h3 class="font-bold text-slate-800 text-sm mt-1">{{ $preset['name'] }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $preset['sessions'] }} {{ $preset['sessions'] === 1 ? 'Session' : 'Sessions' }} • {{ $preset['duration_minutes'] }} mins each</p>
                    </div>
                    <div class="mt-3 pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px] text-slate-400">
                        <span>{{ $preset['validity_days'] }} Calendar Days</span>
                        <span class="font-semibold text-amber-600">Select &rarr;</span>
                    </div>
                </button>
            @endforeach
        </div>

        @if($selectedStudent)
            <!-- Enrollment Form for Selected Student -->
            <form action="{{ route('admin.students.packages.store', ['student' => $selectedStudent->id]) }}" method="POST" class="pt-6 border-t border-slate-100 space-y-4">
                @csrf
                <input type="hidden" name="preset_key" :value="selectedPreset">
                <input type="hidden" name="package_idempotency_key" :value="idempotencyKey">

                <div class="text-sm font-bold text-slate-800">Enrollment & Terms Details</div>

                @if($diagnosticEligibility && $diagnosticEligibility['eligible'])
                    <div class="p-3.5 bg-emerald-50 rounded-xl border border-emerald-200 flex items-center justify-between"
                         x-show="selectedPreset === 'foundation_track' || selectedPreset === 'fluency_track'">
                        <div>
                            <div class="text-xs font-bold text-emerald-900">48-Hour Diagnostic Credit Active (-$25.00)</div>
                            <div class="text-[11px] text-emerald-700">Student completed Diagnostic Session within the last 48 hours. $25.00 automatically credited.</div>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="applyDiagnosticCredit" @change="toggleDiagnosticCredit()" class="rounded text-amber-600 focus:ring-amber-500 h-4 w-4">
                            <span class="text-xs font-semibold text-emerald-900">Apply Credit</span>
                        </label>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Package Name</label>
                        <input type="text" name="package_name" x-model="packageName" required
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Total Sessions</label>
                        <input type="number" name="total_sessions_allocated" x-model="sessionCount" min="1" max="500" required
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Currency</label>
                        <input type="text" name="currency" x-model="currency" required maxlength="3"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none uppercase font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Original Price ($)</label>
                        <input type="text" name="original_price" x-model="originalPrice" @input="recalculateCustom()" required
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Discount Amount ($)</label>
                        <input type="text" name="discount_amount" x-model="discountAmount" @input="recalculateCustom()" required
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Net Invoice Price ($)</label>
                        <input type="text" readonly :value="finalPrice"
                               class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-300 rounded-xl text-sm font-bold text-slate-800 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Expiration Date (Cairo)</label>
                        <input type="date" name="expiration_date" x-model="expirationDate"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Administrative Notes / Override Rationale (Optional)</label>
                    <input type="text" name="override_notes" placeholder="Note custom agreement, special discount rationale, or negotiated terms..."
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold shadow-sm transition-colors">
                        Create Package & Grant Sessions
                    </button>
                </div>
            </form>
        @else
            <div class="pt-6 border-t border-slate-100 text-center text-xs text-slate-400">
                Select a student above to configure enrollment, apply diagnostic credits, or record payments.
            </div>
        @endif
    </div>

    @if($selectedStudent)
        <!-- Student Active Packages & Balances -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <h2 class="text-lg font-bold font-serif text-slate-900">Active Packages & Installments Ledger</h2>

            @if($selectedStudent->packages->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400 border border-dashed border-slate-200 rounded-2xl">
                    No session packages have been assigned to this student yet. Use the presets above to create one.
                </div>
            @else
                <div class="space-y-6">
                    @foreach($selectedStudent->packages as $pkg)
                        <div class="p-6 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4">
                            <!-- Package Header -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-bold text-slate-900 text-base">{{ $pkg->package_name }}</h3>
                                        @if($pkg->is_paid_in_full)
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">Paid in Full</span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">Partial Payment (Owes ${{ $pkg->remaining_balance }})</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">Created {{ $pkg->created_at->format('M j, Y') }} • Expires {{ $pkg->expiration_date ? $pkg->expiration_date->format('M j, Y') : 'Never' }}</p>
                                </div>
                                <div class="flex items-center gap-4 text-xs font-mono">
                                    <div>Original Price: <span class="font-bold">${{ $pkg->original_price }}</span></div>
                                    <div>Discount: <span class="font-bold">${{ $pkg->discount_amount }}</span></div>
                                    <div>Amount Paid (net): <span class="font-bold">${{ $pkg->net_paid }}</span></div>
                                    <div>Final Price: <span class="font-bold text-slate-800">${{ $pkg->final_price }}</span></div>
                                    @if(bccomp($pkg->overpaid, '0.00', 2) > 0)<div>Overpaid: <span class="font-bold text-amber-700">${{ $pkg->overpaid }}</span></div>@else
                                    <div>Remaining Due: <span class="font-bold {{ bccomp($pkg->remaining_balance, '0.00', 2) > 0 ? 'text-amber-600' : 'text-emerald-600' }}">${{ $pkg->remaining_balance }}</span></div>@endif
                                    <div>Available Credits: <span class="font-bold text-slate-800">{{ $pkg->available_credits }}</span></div>
                                </div>
                            </div>

                            <!-- Payment History -->
                            <div class="space-y-2">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Payments & Manual Refunds</div>
                                @if($pkg->payments->isEmpty())
                                    <p class="text-xs text-slate-400 italic">No payments logged yet.</p>
                                @else
                                    <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl bg-white overflow-hidden">
                                        @foreach($pkg->payments as $pmt)
                                            @php $paymentRefunds = $pkg->refunds->where('payment_record_id', $pmt->id)->sortBy('refunded_at'); @endphp
                                            <div data-payment-id="{{ $pmt->id }}" class="p-3 space-y-3 text-xs">
                                              <div class="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <span class="font-bold font-mono text-slate-900">${{ $pmt->amount_paid }} {{ $pmt->currency }}</span>
                                                    <span class="text-slate-400 ml-2">via {{ $pmt->payment_method }}</span>
                                                    @if($pmt->transaction_reference)
                                                        <span class="text-slate-400 ml-1">({{ $pmt->transaction_reference }})</span>
                                                    @endif
                                                    <span class="text-slate-400 ml-2">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($pmt->paid_at) }}</span>
                                                    <span class="text-slate-500 ml-2">Payment #{{ $pmt->id }}</span>
                                                    @if($paymentRefunds->isNotEmpty())
                                                        <span class="ml-2 inline-flex rounded-full bg-rose-50 px-2 py-1 font-semibold text-rose-700">{{ bccomp($pmt->refundable_amount, '0.00', 2) === 0 ? 'Fully refunded' : 'Partially refunded' }}</span>
                                                    @endif
                                                </div>
                                                @if(bccomp($pmt->refundable_amount, '0.00', 2) > 0)
                                                <button type="button" @click="refundPaymentId = {{ $pmt->id }}; refundPackageId = {{ $pkg->id }}; refundAmount = '{{ $pmt->refundable_amount }}'"
                                                        class="text-xs text-rose-600 hover:text-rose-800 font-semibold transition-colors">
                                                    Record Manual Refund (up to ${{ $pmt->refundable_amount }}) &rarr;
                                                </button>
                                                @endif
                                              </div>
                                              @foreach($paymentRefunds as $refund)
                                                <div data-refund-id="{{ $refund->id }}" class="rounded-lg border border-rose-100 bg-rose-50 p-3 text-rose-900 space-y-1">
                                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                        <span class="font-semibold">Manual Refund #{{ $refund->id }}</span>
                                                        <span class="font-bold font-mono">-${{ $refund->amount_refunded }} {{ $refund->currency }}</span>
                                                        <span>Refunded {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($refund->refunded_at) }}</span>
                                                    </div>
                                                    <p>Against payment #{{ $pmt->id }}@if($pmt->transaction_reference) · Reference: {{ $pmt->transaction_reference }}@endif</p>
                                                    @if($refund->reason)<p>Reason: {{ $refund->reason }}</p>@endif
                                                </div>
                                              @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <!-- Action Forms Bar -->
                            <div class="pt-3 border-t border-slate-200 flex flex-wrap items-center gap-3">
                                <!-- Record Payment Form -->
                                <form action="{{ route('admin.students.payments.store', ['student' => $selectedStudent->id, 'package' => $pkg->id]) }}" method="POST" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="payment_idempotency_key_{{ $pkg->id }}" :value="paymentIdempotency">
                                    <input type="text" name="amount_paid" placeholder="Amount (e.g. {{ $pkg->remaining_balance }})" required
                                           class="w-32 px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                    <select name="payment_method" class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                        <option value="PayPal - Manual">PayPal</option>
                                        <option value="InstaPay (Egypt)">InstaPay (Egypt)</option>
                                        <option value="Bank Wire">Bank Wire</option>
                                        <option value="Vodafone Cash">Vodafone Cash</option>
                                        <option value="Wise">Wise</option>
                                        <option value="Cash">Cash</option>
                                    </select>
                                    <input type="text" name="transaction_reference" placeholder="Ref / Trans ID"
                                           class="w-32 px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                    <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors">
                                        + Record Payment
                                    </button>
                                </form>

                                <!-- Courtesy Adjustment Trigger -->
                                <button type="button" @click="courtesyPackageId = {{ $pkg->id }}"
                                        class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl text-xs font-semibold transition-colors ml-auto">
                                    Grant Courtesy Credit
                                </button>
                            </div>

                            <!-- Refund Modal / Collapsible -->
                            <div x-show="refundPackageId === {{ $pkg->id }}" class="p-4 bg-rose-50 rounded-xl border border-rose-200 space-y-3">
                                <div class="text-xs font-bold text-rose-900">Issue Refund for Payment #<span x-text="refundPaymentId"></span></div>
                                <form action="{{ route('admin.students.refunds.store', ['student' => $selectedStudent->id, 'payment' => 0]) }}"
                                      :action="@js(route('admin.students.refunds.store', ['student' => $selectedStudent->id, 'payment' => 0])).replace('/0/refunds', '/' + refundPaymentId + '/refunds')"
                                      method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                    @csrf
                                    <input type="hidden" name="refund_idempotency_key" :value="refundIdempotency">
                                    <div>
                                        <label class="block text-[10px] uppercase font-bold text-rose-800 mb-1">Refund Amount ($)</label>
                                        <input type="text" name="amount_refunded" x-model="refundAmount" required
                                               class="w-full px-3 py-1.5 bg-white border border-rose-300 rounded-lg text-xs font-mono focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase font-bold text-rose-800 mb-1">Forfeit Unused Credits</label>
                                        <input type="number" name="forfeit_credits" x-model="forfeitCredits" min="0" max="50"
                                               class="w-full px-3 py-1.5 bg-white border border-rose-300 rounded-lg text-xs font-mono focus:outline-none">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] uppercase font-bold text-rose-800 mb-1">Refund Reason</label>
                                        <input type="text" name="reason" placeholder="Reason for refund..."
                                               class="w-full px-3 py-1.5 bg-white border border-rose-300 rounded-lg text-xs focus:outline-none">
                                    </div>
                                    <div class="sm:col-span-4 flex justify-end gap-2">
                                        <button type="button" @click="refundPackageId = null; refundPaymentId = null" class="px-3 py-1 bg-white text-slate-600 rounded-lg text-xs border border-slate-300">Cancel</button>
                                        <button type="submit" class="px-4 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold">Save Manual Refund</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Courtesy Adjustment Collapsible -->
                            <div x-show="courtesyPackageId === {{ $pkg->id }}" class="p-4 bg-amber-50 rounded-xl border border-amber-200 space-y-3">
                                <div class="text-xs font-bold text-amber-900">Grant Courtesy or Makeup Credit</div>
                                <form action="{{ route('admin.students.credits.adjust', ['student' => $selectedStudent->id, 'package' => $pkg->id]) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                    @csrf
                                    <input type="hidden" name="credit_idempotency_key_{{ $pkg->id }}" :value="courtesyIdempotency">
                                    <div>
                                        <label class="block text-[10px] uppercase font-bold text-amber-800 mb-1">Credit Change</label>
                                        <input type="number" name="credit_change" value="1" min="-10" max="10" required
                                               class="w-full px-3 py-1.5 bg-white border border-amber-300 rounded-lg text-xs font-mono focus:outline-none">
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="block text-[10px] uppercase font-bold text-amber-800 mb-1">Description / Reason</label>
                                        <input type="text" name="description" placeholder="e.g. Courtesy makeup credit for tutor rescheduled session" required
                                               class="w-full px-3 py-1.5 bg-white border border-amber-300 rounded-lg text-xs focus:outline-none">
                                    </div>
                                    <div class="sm:col-span-4 flex justify-end gap-2">
                                        <button type="button" @click="courtesyPackageId = null" class="px-3 py-1 bg-white text-slate-600 rounded-lg text-xs border border-slate-300">Cancel</button>
                                        <button type="submit" class="px-4 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold">Apply Adjustment</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
