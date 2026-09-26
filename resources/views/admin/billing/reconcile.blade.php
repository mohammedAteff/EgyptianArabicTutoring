@extends('layouts.admin')

@section('content')
<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Billing Reconciliation Diagnostic</h1>
            <p class="text-sm text-slate-500 mt-1">Read-only structural integrity audit across student packages, payments, refunds, and append-only session ledgers.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.billing.cashier') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Back to Cashier</span>
            </a>
            <a href="{{ route('admin.billing.reconcile.export', ['format' => 'csv']) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-xl text-xs font-semibold shadow-xs transition-colors">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export CSV</span>
            </a>
            <a href="{{ route('admin.billing.reconcile.export', ['format' => 'xlsx']) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-semibold shadow-xs transition-colors">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export XLSX</span>
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Status Card -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Ledger Integrity</div>
            <div class="flex items-center gap-2 mt-2">
                @if($report['has_discrepancies'])
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                        {{ $report['total_discrepancies_count'] }} Issues Detected
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        100% Reconciled
                    </span>
                @endif
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Read-only diagnostic; historical ledger records remain unmutated.</p>
        </div>

        <!-- Negative Balances -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Negative Balances</div>
            <div class="text-2xl font-bold font-mono {{ $report['summary']['negative_balances_count'] > 0 ? 'text-rose-600' : 'text-slate-800' }}">
                {{ $report['summary']['negative_balances_count'] }}
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Packages where net paid exceeds final invoice price.</p>
        </div>

        <!-- Excess Refunds -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Excess Refunds</div>
            <div class="text-2xl font-bold font-mono {{ $report['summary']['excess_refunds_count'] > 0 ? 'text-rose-600' : 'text-slate-800' }}">
                {{ $report['summary']['excess_refunds_count'] }}
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Payments where total refunds exceed original paid amount.</p>
        </div>

        <!-- Credit Mismatches & Inconsistencies -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Credit / Owner Inconsistencies</div>
            <div class="text-2xl font-bold font-mono {{ ($report['summary']['credit_mismatches_count'] + $report['summary']['ownership_inconsistencies_count']) > 0 ? 'text-rose-600' : 'text-slate-800' }}">
                {{ $report['summary']['credit_mismatches_count'] + $report['summary']['ownership_inconsistencies_count'] }}
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Allocated vs ledger grants or ownership relation drift.</p>
        </div>
    </div>

    <!-- Diagnostic Results Detail -->
    @if(!$report['has_discrepancies'])
        <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-xs space-y-3">
            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 mx-auto flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h2 class="text-lg font-bold text-slate-900">All Financial Ledgers Are Perfectly Balanced</h2>
            <p class="text-xs text-slate-500 max-w-lg mx-auto">
                No negative package balances, no over-refunded payments, no credit grant mismatches, and no ownership inconsistencies were found across any database records.
            </p>
        </div>
    @else
        <!-- Check 1: Negative Balances -->
        @if(!empty($report['negative_balances']))
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-rose-200 shadow-xs space-y-4">
                <h2 class="text-base font-bold text-rose-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                    <span>Negative Derived Package Balances ({{ count($report['negative_balances']) }})</span>
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-400 uppercase font-semibold">
                                <th class="py-2.5 px-3">Pkg ID</th>
                                <th class="py-2.5 px-3">Student</th>
                                <th class="py-2.5 px-3">Package Name</th>
                                <th class="py-2.5 px-3 font-mono">Final Price</th>
                                <th class="py-2.5 px-3 font-mono">Net Paid</th>
                                <th class="py-2.5 px-3 font-mono">Remaining Balance</th>
                                <th class="py-2.5 px-3">Issue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @foreach($report['negative_balances'] as $nb)
                                <tr>
                                    <td class="py-2.5 px-3 text-slate-500">#{{ $nb['package_id'] }}</td>
                                    <td class="py-2.5 px-3 font-sans font-medium text-slate-900">{{ $nb['student_name'] }}</td>
                                    <td class="py-2.5 px-3 font-sans text-slate-600">{{ $nb['package_name'] }}</td>
                                    <td class="py-2.5 px-3">${{ $nb['final_price'] }}</td>
                                    <td class="py-2.5 px-3">${{ $nb['net_paid'] }}</td>
                                    <td class="py-2.5 px-3 text-rose-600 font-bold">${{ $nb['remaining_balance'] }}</td>
                                    <td class="py-2.5 px-3 font-sans text-slate-500">{{ $nb['issue'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Check 2: Excess Refunds -->
        @if(!empty($report['excess_refunds']))
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-rose-200 shadow-xs space-y-4">
                <h2 class="text-base font-bold text-rose-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                    <span>Payments with Excess Refunds ({{ count($report['excess_refunds']) }})</span>
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-400 uppercase font-semibold">
                                <th class="py-2.5 px-3">Payment ID</th>
                                <th class="py-2.5 px-3">Student</th>
                                <th class="py-2.5 px-3 font-mono">Amount Paid</th>
                                <th class="py-2.5 px-3 font-mono">Total Refunded</th>
                                <th class="py-2.5 px-3 font-mono">Excess</th>
                                <th class="py-2.5 px-3">Issue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @foreach($report['excess_refunds'] as $er)
                                <tr>
                                    <td class="py-2.5 px-3 text-slate-500">#{{ $er['payment_id'] }}</td>
                                    <td class="py-2.5 px-3 font-sans font-medium text-slate-900">{{ $er['student_name'] }}</td>
                                    <td class="py-2.5 px-3">${{ $er['amount_paid'] }}</td>
                                    <td class="py-2.5 px-3">${{ $er['total_refunded'] }}</td>
                                    <td class="py-2.5 px-3 text-rose-600 font-bold">${{ $er['excess_amount'] }}</td>
                                    <td class="py-2.5 px-3 font-sans text-slate-500">{{ $er['issue'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Check 3: Credit Allocation Mismatches -->
        @if(!empty($report['credit_mismatches']))
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-amber-200 shadow-xs space-y-4">
                <h2 class="text-base font-bold text-amber-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <span>Credit Allocation vs Ledger Grant Mismatches ({{ count($report['credit_mismatches']) }})</span>
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-400 uppercase font-semibold">
                                <th class="py-2.5 px-3">Pkg ID</th>
                                <th class="py-2.5 px-3">Student</th>
                                <th class="py-2.5 px-3">Package Name</th>
                                <th class="py-2.5 px-3 font-mono">Allocated Sessions</th>
                                <th class="py-2.5 px-3 font-mono">Granted Credits</th>
                                <th class="py-2.5 px-3 font-mono">Delta</th>
                                <th class="py-2.5 px-3">Issue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @foreach($report['credit_mismatches'] as $cm)
                                <tr>
                                    <td class="py-2.5 px-3 text-slate-500">#{{ $cm['package_id'] }}</td>
                                    <td class="py-2.5 px-3 font-sans font-medium text-slate-900">{{ $cm['student_name'] }}</td>
                                    <td class="py-2.5 px-3 font-sans text-slate-600">{{ $cm['package_name'] }}</td>
                                    <td class="py-2.5 px-3">{{ $cm['allocated_sessions'] }}</td>
                                    <td class="py-2.5 px-3">{{ $cm['granted_credits'] }}</td>
                                    <td class="py-2.5 px-3 text-amber-600 font-bold">{{ $cm['delta'] }}</td>
                                    <td class="py-2.5 px-3 font-sans text-slate-500">{{ $cm['issue'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Check 4: Ownership Inconsistencies -->
        @if(!empty($report['ownership_inconsistencies']))
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-amber-200 shadow-xs space-y-4">
                <h2 class="text-base font-bold text-amber-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <span>Ownership Inconsistencies ({{ count($report['ownership_inconsistencies']) }})</span>
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-400 uppercase font-semibold">
                                <th class="py-2.5 px-3">Type</th>
                                <th class="py-2.5 px-3">Record ID</th>
                                <th class="py-2.5 px-3">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($report['ownership_inconsistencies'] as $oi)
                                <tr>
                                    <td class="py-2.5 px-3 font-semibold text-slate-900">{{ $oi['type'] }}</td>
                                    <td class="py-2.5 px-3 font-mono text-slate-500">#{{ $oi['record_id'] }}</td>
                                    <td class="py-2.5 px-3 text-slate-600">{{ $oi['details'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

</div>
@endsection
