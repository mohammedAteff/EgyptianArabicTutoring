@extends('layouts.admin')
@section('content')
<div class="space-y-6">
<div><h1 class="text-2xl font-bold font-serif text-slate-900">Billing Reconciliation Diagnostic</h1><p class="mt-2 text-sm text-slate-500">Read-only integrity snapshot. Filters select existing discrepancies; dates do not apply to this snapshot.</p><a class="text-sm underline" href="{{ route('admin.billing.cashier') }}">Back to Cashier</a></div>
<x-report-filters :filters="$filters" :fields="['q' => ['Search student / package / issue', 'search'], 'student_id' => ['Student ID', 'number'], 'category' => ['Problem type', \App\Domains\Students\Services\ReconciliationReport::CATEGORIES]]" :action="route('admin.billing.reconcile')" export-route="admin.billing.reconcile.export" />
<p class="text-sm text-slate-600">{{ $rows->count() }} matching issues · Full snapshot: {{ $report['status_label'] }} ({{ $report['total_discrepancies_count'] }} issues).</p>
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr>@foreach($headers as $header)<th class="px-4 py-3 font-semibold">{{ $header }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">@forelse($rows as $row)<tr>@foreach($row as $cell)<td class="px-4 py-3">{{ $cell }}</td>@endforeach</tr>@empty<tr><td colspan="6" class="px-4 py-8 text-slate-500">No discrepancies match these filters.</td></tr>@endforelse</tbody></table></div>
</div>
@endsection
