@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-4xl space-y-6"><a class="text-sm font-semibold text-amber-800" href="{{ route('admin.development-tools.index') }}">← Development & Launch Tools</a><h1 class="font-serif text-3xl font-bold">Operation {{ $operation->status }}</h1><p class="text-sm text-slate-600">{{ ucfirst($operation->type) }} · {{ $operation->domain }} · #{{ $operation->id }}</p>
@if($operation->status === 'completed')
<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Completion reconciliation</h2><p class="mt-3 text-sm">The selected operation completed. Existing different records were not overwritten.</p>
@foreach($operation->summary ?? [] as $key => $value)
    @if(is_scalar($value) && ! in_array($key, ['operation_id', 'type', 'domain'], true))<p class="mt-3 break-all text-sm"><span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span> {{ $value }}</p>@elseif(is_array($value))<div class="mt-4"><h3 class="text-sm font-semibold">{{ ucfirst(str_replace('_', ' ', $key)) }}</h3><dl class="mt-2 space-y-2 rounded-xl bg-slate-50 p-3 text-sm">@foreach($value as $label => $detail)@if(is_scalar($detail))<div class="flex flex-wrap justify-between gap-2"><dt>{{ ucfirst(str_replace('_', ' ', (string) $label)) }}</dt><dd class="break-all font-semibold">{{ $detail }}</dd></div>@endif@endforeach</dl></div>@endif
@endforeach
@if($operation->type !== 'import' && $operation->archive_path && isset($operation->summary['archive_sha256']))<a class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white" href="{{ route('admin.development-tools.download', $operation) }}">Download private portable export</a>@endif
</section>
@else<p class="rounded-xl border border-slate-200 bg-white p-4 text-sm">This preview has not completed an operation. Build a fresh preview from the tools page if its token expired.</p>@endif
</div>
@endsection
