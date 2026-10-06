@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div><p class="text-sm font-semibold text-red-700">System · Super Admin only</p><h1 class="mt-2 font-serif text-3xl font-bold">Development & Launch Tools</h1><p class="mt-2 text-sm text-slate-600">Prepare a clean development environment. Preview first, then confirm with your current password and security factor. These tools permanently remove the selected history.</p></div>
    <section class="rounded-2xl border-2 border-red-300 bg-red-50 p-6">
        <h2 class="text-xl font-bold text-red-900">Danger Zone</h2><p class="mt-2 text-sm text-red-900">Irreversible deletion. A protected system recovery snapshot is {{ config('development_tools.require_snapshot') ? 'mandatory' : 'optional' }}. Resets preserve the application and configuration; they do not deploy or change security settings.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
        @foreach($domains as $domain => $phrase)
            <form method="POST" action="{{ route('admin.development-tools.preview') }}" class="space-y-3 rounded-xl border border-red-200 bg-white p-4">
                @csrf<input type="hidden" name="type" value="reset"><input type="hidden" name="domain" value="{{ $domain }}">
                <h3 class="font-bold">Reset {{ $domain === 'financial' ? 'financial test history' : ($domain === 'backups' ? 'managed local backups' : $domain) }}</h3>
                @if($domain === 'resources')
                <div class="grid gap-2 text-sm">
                    <label><input type="checkbox" name="scope[resources]" value="1" checked> Resources</label>
                    <label><input type="checkbox" name="scope[categories]" value="1"> Categories</label>
                    <label><input type="checkbox" name="scope[history]" value="1" checked> Request / download history</label>
                    <label><input type="checkbox" name="scope[files]" value="1" checked> Private resource files</label>
                </div>
                @endif
                <label class="block text-sm"><input type="checkbox" name="scope[export_before]" value="1" checked> Export selected data before reset</label>
                <label class="block text-sm"><input type="checkbox" name="scope[snapshot]" value="1" checked @disabled(config('development_tools.require_snapshot'))> Protected system recovery snapshot</label>
                <button class="min-h-11 rounded-xl border border-red-300 px-4 py-2 text-sm font-semibold text-red-800">Preview reset scope</button>
            </form>
        @endforeach
        </div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-bold">Export Everything / Custom Export</h2><p class="mt-2 text-sm text-slate-600">All modules are checked for Export Everything. Uncheck modules for a custom export. Required parent records are included automatically; credentials and active authentication sessions are excluded.</p>
        <form method="POST" action="{{ route('admin.development-tools.preview') }}" class="mt-5 space-y-4">
            @csrf<input type="hidden" name="type" value="export"><input type="hidden" name="domain" value="selection">
            <div class="grid gap-3 sm:grid-cols-2">@foreach($modules as $module => $label)<label class="text-sm"><input type="checkbox" name="scope[modules][]" value="{{ $module }}" checked> {{ $label }}</label>@endforeach</div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-semibold">Student ID (where applicable)<input type="number" min="1" name="scope[filters][student_id]" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
                <label class="text-sm font-semibold">Currency (financial records)<input name="scope[filters][currency]" maxlength="3" placeholder="USD" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
                <label class="text-sm font-semibold">Analytics from (business date)<input type="date" name="scope[filters][date_from]" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
                <label class="text-sm font-semibold">Analytics through (business date)<input type="date" name="scope[filters][date_to]" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
            </div>
            <fieldset class="space-y-2"><legend class="mb-2 text-sm font-semibold">Managed snapshots: select specific archives or leave unchecked for all managed snapshots</legend>@forelse($snapshots as $snapshot)<label class="block break-all text-sm"><input type="checkbox" name="scope[backup_filenames][]" value="{{ $snapshot['filename'] }}"> {{ $snapshot['filename'] }}</label>@empty<p class="text-sm text-slate-500">No positively identified managed snapshots.</p>@endforelse</fieldset>
            <button class="min-h-11 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Preview export</button>
        </form>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-bold">Import preview</h2><p class="mt-2 text-sm text-slate-600">Upload a compatible portable export to inspect hashes, counts and dependencies. Upload never writes business records. Choose modules, rebuild the selected preview, then confirm. Restore missing / skip identical is the only policy; existing different records are never overwritten.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.development-tools.upload') }}" class="mt-5 space-y-3">@csrf<label class="block text-sm font-semibold">Portable ZIP archive<input type="file" name="archive" accept=".zip,application/zip" required class="mt-2 block w-full text-sm"></label><button class="min-h-11 rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold">Inspect archive</button></form>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Recent operations</h2><div class="mt-4 space-y-2">@forelse($history as $operation)<a href="{{ route('admin.development-tools.show', $operation) }}" class="block text-sm font-semibold text-amber-800">{{ ucfirst($operation->type) }} · {{ $operation->domain }} · {{ $operation->status }} · {{ $operation->created_at->format('Y-m-d H:i') }}</a>@empty<p class="text-sm text-slate-500">No operations recorded.</p>@endforelse</div></section>
</div>
@endsection
