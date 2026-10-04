@extends('layouts.admin')

@section('content')
<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">System Health & Diagnostics</h1>
            <p class="text-sm text-slate-500 mt-1">Real-time status of backend services, scheduler heartbeat, queues, MySQL storage, and server clocks.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $environment === 'production' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                <span class="w-2 h-2 rounded-full {{ $environment === 'production' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                <span>Env: {{ strtoupper($environment) }}</span>
            </span>
            @if($debugMode)
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">Debug Enabled</span>
            @endif
        </div>
    </div>

    <!-- Health Diagnostics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- 1. MySQL / MariaDB -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Database</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $dbStatus === 'healthy' ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
            </div>
            <div class="text-lg font-bold text-slate-900">MariaDB / InnoDB</div>
            <div class="text-xs text-slate-600 mt-1 font-medium">{{ $dbMessage }}</div>
            @if($dbDetails)
                <div class="text-[11px] text-slate-400 mt-2">{!! $dbDetails !!}</div>
            @endif
        </div>

        <!-- 2. Scheduler Heartbeat -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Scheduler</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $schedulerStatus === 'healthy' ? 'bg-emerald-500' : ($schedulerStatus === 'warning' ? 'bg-amber-500' : 'bg-red-500') }}"></span>
            </div>
            <div class="text-lg font-bold text-slate-900">Cron Heartbeat</div>
            <div class="text-xs font-medium {{ $schedulerStatus === 'healthy' ? 'text-emerald-700' : ($schedulerStatus === 'warning' ? 'text-amber-700' : 'text-red-700') }} mt-1">
                {{ $schedulerMessage }}
            </div>
            @if($schedulerDetails)
                <div class="text-[11px] text-slate-400 mt-2">{{ $schedulerDetails }}</div>
            @endif
        </div>

        <!-- 3. Queue & Background Jobs -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Queue Worker</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $queueStatus === 'healthy' ? 'bg-emerald-500' : ($queueStatus === 'warning' ? 'bg-amber-500' : 'bg-red-500') }}"></span>
            </div>
            <div class="text-lg font-bold text-slate-900">Async Jobs</div>
            <div class="text-xs font-medium {{ $queueStatus === 'healthy' ? 'text-slate-600' : ($queueStatus === 'warning' ? 'text-amber-700' : 'text-red-700') }} mt-1">
                {{ $queueMessage }}
            </div>
            <div class="text-[11px] text-slate-400 mt-2">
                Pending: {{ $pendingJobsCount }} &bull; Failed: {{ $failedJobsCount }}
            </div>
        </div>

        <!-- 4. Storage & Disk Usage -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Disk & Storage</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $storageStatus === 'healthy' ? 'bg-emerald-500' : ($storageStatus === 'warning' ? 'bg-amber-500' : 'bg-red-500') }}"></span>
            </div>
            <div class="text-lg font-bold text-slate-900">Filesystem</div>
            <div class="text-xs text-slate-600 mt-1 font-medium">{{ $storageMessage }}</div>
            @if($diskDetails)
                <div class="text-[11px] text-slate-400 mt-2">{{ $diskDetails }}</div>
            @endif
        </div>

        <!-- 5. Backups & Disaster Recovery -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Backups</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $backupStatus === 'healthy' ? 'bg-emerald-500' : ($backupStatus === 'warning' ? 'bg-amber-500' : 'bg-red-500') }}"></span>
            </div>
            <div class="text-lg font-bold text-slate-900">
                <a href="{{ route('admin.backups.index') }}" class="hover:text-amber-600 transition-colors">Recovery Snapshots &rarr;</a>
            </div>
            <div class="text-xs text-slate-600 mt-1 font-medium">{{ $backupMessage }}</div>
            @if($lastBackupFile)
                <div class="text-[11px] text-slate-400 mt-2 font-mono truncate" title="{{ $lastBackupFile }}">{{ $lastBackupFile }}</div>
            @endif
        </div>

        <!-- 6. Mail Delivery -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Mail Delivery</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $mailStatus === 'healthy' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
            </div>
            <div class="text-lg font-bold text-slate-900">Email System</div>
            <div class="text-xs text-slate-600 mt-1 font-medium">{{ $mailMessage }}</div>
            <div class="text-[11px] text-slate-400 mt-2 truncate">Sender: {{ $mailFrom }}</div>
        </div>
    </div>

    <!-- Maintenance Mode Traffic Diagnostics (Phase 5) -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-bold font-serif text-slate-900">Maintenance Mode Traffic Diagnostics</h2>
                <p class="text-xs text-slate-500 mt-0.5">Captures and reports all inbound visitor traffic intercepted while maintenance mode was active.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.health.maintenance-visitors.export', array_merge($filters, ['format' => 'csv'])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold border border-slate-200 transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export CSV</span>
                </a>
                <a href="{{ route('admin.health.maintenance-visitors.export', array_merge($filters, ['format' => 'xlsx'])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 rounded-xl text-xs font-semibold border border-emerald-200 transition-colors">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export XLSX</span>
                </a>
            </div>
        </div>

        <x-report-filters :filters="$filters" :fields="array_merge(app(\App\Domains\Reporting\Services\ReportPeriod::class)->fields(), ['country'=>['Country code','text'],'path'=>['Page / path contains','search']])" :action="route('admin.health')" export-route="admin.health.maintenance-visitors.export" />
        <div class="overflow-x-auto rounded-xl border border-slate-200"><table class="w-full text-left text-xs"><thead class="bg-slate-50"><tr><th class="p-3">Timestamp (UTC)</th><th class="p-3">Visitor ID</th><th class="p-3">Country</th><th class="p-3">Requested URL</th><th class="p-3">Referrer</th></tr></thead><tbody>@foreach($maintenanceRows as $visit)<tr><td class="p-3">{{ $visit->created_at }}</td><td class="p-3">{{ $visit->visitor_id }}</td><td class="p-3">{{ $visit->country_code }}</td><td class="p-3">{{ $visit->url }}</td><td class="p-3">{{ $visit->referrer ?: 'Direct / None' }}</td></tr>@endforeach</tbody></table></div>
        {{ $maintenanceRows->links() }}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Intercepted Hits</span>
                <span class="font-mono text-2xl font-bold text-slate-900 block mt-1">{{ number_format($maintenanceHitsCount) }}</span>
            </div>
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Unique Visitors</span>
                <span class="font-mono text-2xl font-bold text-slate-900 block mt-1">{{ number_format($maintenanceUniqueVisitors) }}</span>
            </div>
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Maintenance Bounce Rate</span>
                <span class="font-mono text-2xl font-bold text-amber-600 block mt-1">{{ number_format($maintenanceBounceRate, 1) }}%</span>
            </div>
        </div>

        @if($maintenanceCountries->isNotEmpty())
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Country Segmentation (Top 10)</h3>
                <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 border-b border-slate-200 font-bold uppercase tracking-wider text-[11px] text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Country Code</th>
                                <th class="px-4 py-2.5">Hits</th>
                                <th class="px-4 py-2.5">Unique Visitors</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($maintenanceCountries as $mc)
                                <tr>
                                    <td class="px-4 py-2 font-mono font-bold text-slate-800">{{ $mc->country_code }}</td>
                                    <td class="px-4 py-2 font-mono">{{ number_format($mc->hits) }}</td>
                                    <td class="px-4 py-2 font-mono">{{ number_format($mc->visitors) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <!-- Server Environment Info -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
        <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">Environment & Clocks</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">PHP Version</span>
                <span class="font-mono text-sm font-bold text-slate-900">{{ $phpVersion }}</span>
            </div>

            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Laravel Version</span>
                <span class="font-mono text-sm font-bold text-slate-900">{{ $laravelVersion }}</span>
            </div>

            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Server UTC Clock</span>
                <span class="font-mono text-xs font-bold text-slate-900 block mt-0.5 truncate whitespace-nowrap">{{ $serverTimeUtc }} UTC</span>
            </div>

            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Business Clock</span>
                <span class="font-mono text-xs font-bold text-amber-700 block mt-0.5 truncate whitespace-nowrap">{{ $cairoTime }}</span>
            </div>
        </div>
    </div>

</div>
@endsection
