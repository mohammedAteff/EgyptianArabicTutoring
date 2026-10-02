@extends('layouts.admin')

@section('content')
<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Database & Asset Backups</h1>
            <p class="text-sm text-slate-500 mt-1">Manage transactional database dumps and private asset archives with retention enforcement.</p>
        </div>
        <form action="{{ route('admin.backups.create') }}" method="POST" onsubmit="return confirm('Generate a complete database and asset backup now?');">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <span>Run Backup Now</span>
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Backup Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-6">
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Last Backup Status</div>
            <div class="text-lg font-bold {{ str_starts_with($lastBackupStatus, 'success') ? 'text-emerald-700' : ($lastBackupStatus === 'never_run' ? 'text-slate-500' : 'text-rose-600') }} capitalize">
                {{ $lastBackupStatus === 'never_run' ? 'Never Run' : (str_starts_with($lastBackupStatus, 'success') ? 'Healthy' : 'Failed') }}
            </div>
            <div class="text-xs text-slate-500 mt-1">
                {{ $lastBackupAt ? $lastBackupAt->diffForHumans() : 'No backup run recorded' }}
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Retention Window</div>
            <div class="text-lg font-bold text-slate-900">{{ $retentionDays }} Days</div>
            <div class="text-xs text-slate-500 mt-1">Configured in system settings</div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Available Disk Space</div>
            <div class="text-lg font-bold text-slate-900">{{ $freeDiskSpace }}</div>
            <div class="text-xs text-slate-500 mt-1">Storage partition capacity</div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Archived Snapshots</div>
            <div class="text-lg font-bold text-slate-900">{{ count($backups) }}</div>
            <div class="text-xs text-slate-500 mt-1">In local backup storage</div>
        </div>
    </div>

    <!-- Backups Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-lg font-bold font-serif text-slate-900">Archived Snapshots</h2>
            <span class="text-xs text-slate-400">Stored at <code class="font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-700">storage/app/backups/</code></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-[11px] uppercase tracking-wider text-slate-500 font-semibold">
                    <tr>
                        <th class="py-3.5 px-6">Archive Filename</th>
                        <th class="py-3.5 px-6">Archive Size</th>
                        <th class="py-3.5 px-6">Created Date (Business Time)</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($backups as $backup)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="py-4 px-6 font-mono text-xs font-semibold text-slate-900 flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span>{{ $backup['filename'] }}</span>
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-600 font-mono">
                                {{ $backup['size_formatted'] }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-500">
                                <div>{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($backup['created_at']) }}</div>
                                <div class="text-[11px] text-slate-400">{{ $backup['created_at']->diffForHumans() }}</div>
                            </td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <a href="{{ route('admin.backups.download', ['filename' => $backup['filename']]) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-semibold transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    <span>Download</span>
                                </a>

                                <form action="{{ route('admin.backups.destroy', ['filename' => $backup['filename']]) }}" method="POST" class="inline" onsubmit="return confirm('Permanently delete this backup archive?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg text-xs font-semibold transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        <span>Delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-400 text-xs">
                                No backup archives generated yet. Click "Run Backup Now" to create your first snapshot.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
