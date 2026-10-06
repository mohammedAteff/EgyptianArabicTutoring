@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a class="text-sm font-semibold text-amber-800" href="{{ route('admin.development-tools.index') }}">← Development & Launch Tools</a>
    <h1 class="font-serif text-3xl font-bold">{{ ucfirst($operation->type) }} preview · {{ $operation->domain }}</h1>
    @if($operation->type === 'reset')
    <section class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
        <p>Portable export before reset: <strong>{{ ! empty($operation->scope['export_before']) ? 'Yes' : 'No' }}</strong></p>
        <p class="mt-2">Protected system recovery snapshot: <strong>{{ config('development_tools.require_snapshot') ? 'Required' : (! empty($operation->scope['snapshot']) ? 'Selected' : 'Not selected') }}</strong></p>
        <p class="mt-2">Private files to remove: <strong>{{ $preview['deletable_file_count'] ?? 0 }}</strong></p>
        @if($operation->domain === 'resources')
        <fieldset class="mt-4 grid gap-2 sm:grid-cols-2"><legend class="mb-2 font-semibold">Confirmed Resource reset scope</legend>
            @foreach(['resources' => 'Resources', 'categories' => 'Categories', 'history' => 'Request / download history', 'files' => 'Private resource files'] as $field => $label)
            <label><input type="checkbox" disabled @checked(! empty($operation->scope[$field]))> {{ $label }}</label>
            @endforeach
        </fieldset><p class="mt-2 text-slate-600">Return to the tools page to change this selection and build a new preview.</p>
        @endif
    </section>
    @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Record / file inventory</h2><div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Record group</th><th class="p-3">Records / proposed action</th></tr></thead><tbody>@forelse($preview['counts'] as $table => $count)<tr class="border-t border-slate-100"><td class="p-3">{{ ucfirst(str_replace('_', ' ', $table)) }}</td><td class="p-3">@if(is_array($count))Restore {{ $count['restore'] }} · Skip identical {{ $count['skip_identical'] }}@else{{ $count }}@endif</td></tr>@empty<tr><td class="p-3" colspan="2">No database rows in this selected scope.</td></tr>@endforelse</tbody></table></div><p class="mt-4 text-sm">Private files: {{ $preview['file_count'] }} · Managed snapshots: {{ $preview['managed_snapshot_count'] ?? $preview['snapshot_count'] ?? 0 }}</p>@foreach($preview['detached_references'] ?? [] as $table => $count)<p class="mt-2 text-sm">{{ $count }} {{ $table }} reference(s) safely detached / withdrawn.</p>@endforeach<p class="mt-2 text-sm">Shared payloads preserved: {{ $preview['shared_files_preserved'] ?? 0 }}.</p>@foreach($preview['warnings'] ?? [] as $warning)<p class="mt-3 text-sm text-slate-600">{{ $warning }}</p>@endforeach</section>
    @if($operation->type === 'import')
        <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Archive modules</h2>
        @if(empty($operation->scope['selection_confirmed']))
        <form method="POST" action="{{ route('admin.development-tools.select-import') }}" class="mt-4 space-y-3">@csrf<input type="hidden" name="operation_token" value="{{ $token }}">@foreach($preview['included_modules'] as $module)<label class="block text-sm"><input type="checkbox" name="modules[]" value="{{ $module }}"> {{ $moduleLabels[$module] }}</label>@endforeach<button class="min-h-11 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Preview chosen restore</button></form>
        @else
        @foreach($operation->scope['modules'] as $module)<p class="mt-3 text-sm">{{ $moduleLabels[$module] }}</p>@endforeach
        @endif
        @foreach($preview['conflicts'] as $conflict)<p class="mt-3 break-words text-sm font-semibold text-red-800">Conflict: {{ $conflict['table'] }} · {{ $conflict['reason'] }}</p>@endforeach
        @foreach($preview['missing_dependencies'] as $missing)<p class="mt-3 break-words text-sm font-semibold text-red-800">Missing dependency: {{ $missing['table'] }} · {{ $missing['reason'] }}</p>@endforeach
        </section>
    @endif
    @if(($preview['student_session_count'] ?? 0) > 0)<p class="rounded-xl border border-slate-200 bg-white p-4 text-sm">{{ $preview['student_session_count'] }} stored student authentication scope(s) will be cleared; staff sign-in stays intact.</p>@endif
    @if($operation->type !== 'import' || (! empty($operation->scope['selection_confirmed']) && $preview['conflicts'] === [] && $preview['missing_dependencies'] === []))
    <section class="rounded-2xl border-2 border-red-300 bg-red-50 p-6">
        <h2 class="text-xl font-bold text-red-900">Final confirmation</h2>
        <p class="mt-2 text-sm font-semibold text-red-900">{{ $operation->type === 'reset' ? 'Irreversible: the previewed data is permanently deleted. Review every count before continuing.' : 'This writes the selected archive or restoration. Review the preview before continuing.' }}</p>
        <p class="mt-3 text-sm">Token expires at {{ $operation->expires_at->format('H:i:s') }} UTC and belongs to this account, session and exact preview. Any record/file change requires a fresh preview.</p>
        <p class="mt-3 break-words font-mono text-sm font-bold">{{ $phrase }}</p>
        <form method="POST" action="{{ route('admin.development-tools.confirm') }}" class="mt-5 space-y-4">
            @csrf<input type="hidden" name="operation_token" value="{{ $token }}">
            <label class="block text-sm font-semibold">Exact confirmation phrase<input name="phrase" required autocomplete="off" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
            <label class="block text-sm font-semibold">Current password<input type="password" name="password" required autocomplete="current-password" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
            <p class="text-sm text-slate-600">If MFA is enabled, enter one fresh authenticator code or one unused recovery code. These values are never included in exports or audit events.</p>
            <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-semibold">Authenticator code<input name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label><label class="text-sm font-semibold">Recovery code<input type="password" name="recovery_code" autocomplete="off" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label></div>
            <button class="min-h-11 rounded-xl bg-red-800 px-5 py-3 text-sm font-semibold text-white">Confirm {{ $operation->type }}</button>
        </form>
    </section>
    @endif
</div>
@endsection
