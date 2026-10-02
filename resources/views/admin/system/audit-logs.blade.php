@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Security & Operations Audit Log</h1>
            <p class="text-sm text-slate-500 mt-1">Immutable security trail of administrative logins, contact merges, and configuration edits.</p>
        </div>
    </div>

    <!-- Audit Log Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3.5 font-semibold">Action</th>
                        <th class="px-4 py-3.5 font-semibold">Administrator</th>
                        <th class="px-4 py-3.5 font-semibold">Entity Type / ID</th>
                        <th class="px-4 py-3.5 font-semibold">IP & Client</th>
                        <th class="px-4 py-3.5 font-semibold text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-4 font-bold text-slate-900">
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-800 rounded-md font-mono text-xs">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs font-semibold text-slate-700">
                                {{ $log->administrator->name ?? 'System / Anonymous' }}
                            </td>
                            <td class="px-4 py-4 text-xs font-mono text-slate-600">
                                @if($log->entity_type)
                                    {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs font-mono text-slate-500">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-right text-xs text-slate-500">
                                {{ $log->created_at ? app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($log->created_at) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-slate-400">
                                <p class="text-sm font-semibold text-slate-600">No audit logs recorded yet.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
