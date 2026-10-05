@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <a href="{{ route('admin.bookings.show', $booking) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-amber-700">&larr; Booking #{{ $booking->id }}</a>
    <div><h1 class="font-serif text-3xl font-bold text-slate-900">Lesson Workspace</h1><p class="mt-2 text-slate-500">Prepare and share learning materials for this lesson.</p></div>
    @if($booking->student)
        <a href="{{ route('admin.students.teaching', ['student' => $booking->student, 'booking' => $booking->id]) }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white">Homework, learning plan and tutor preparation</a>
    @endif
    @foreach(['success' => 'bg-emerald-50 text-emerald-800', 'warning' => 'bg-amber-50 text-amber-900'] as $key => $style)
        @if(session($key))<p role="status" class="rounded-xl p-4 {{ $style }}">{{ session($key) }}</p>@endif
    @endforeach
    @if($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4 text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section aria-label="Lesson details" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="text-xl font-bold text-slate-900">{{ $booking->student?->first_name ?? $booking->contact?->name ?? 'Student' }} {{ $booking->student?->last_name }}</h2>
            <p class="mt-1 text-slate-600">{{ $booking->sessionType?->name ?? 'Private lesson' }} · {{ (int) $booking->start_at_utc->diffInMinutes($booking->end_at_utc) }} minutes</p></div>
            <span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">{{ $booking->studentStatusLabel() }}</span>
        </div>
        <dl class="mt-5 grid gap-5 sm:grid-cols-2">
            <div><dt class="text-sm font-semibold text-slate-500">Business time</dt><dd class="mt-1">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($booking->start_at_utc->copy()->setTimezone($businessTimezone)) }} – {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($booking->end_at_utc->copy()->setTimezone($businessTimezone)) }}<span class="mt-1 block text-sm text-slate-500">{{ $businessTimezone }}</span></dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Student time at booking</dt><dd class="mt-1">{{ $booking->customer_start->format('D, M j, Y · g:i A') }} – {{ $booking->end_at_utc->copy()->setTimezone($booking->customer_timezone)->format('g:i A') }}<span class="mt-1 block text-sm text-slate-500">{{ $booking->customer_timezone }}</span></dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Meeting assignment</dt><dd class="mt-1">{{ $booking->meeting_provider_snapshot ?? 'No provider assigned' }} · {{ $booking->meeting_url_snapshot ? 'Link assigned — view booking for access' : 'No link assigned' }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Delivery</dt><dd class="mt-1">{{ $booking->completed_at ? 'Completed '.$booking->completed_at->format('Y-m-d') : 'No completion recorded' }}</dd></div>
        </dl>
        @if($creditSources->isNotEmpty())
        <div class="mt-5 border-t border-slate-100 pt-4"><h3 class="text-sm font-semibold text-slate-500">Recorded credit source</h3>
            @foreach($creditSources as $source)<p class="mt-2 text-sm">{{ $source->package?->package_name ?? 'Credit ledger' }} · {{ str_replace('_', ' ', $source->entry_type) }} · {{ $source->credit_change }}</p>@endforeach
        </div>
        @endif
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <h2 class="text-xl font-bold text-slate-900">Attach material</h2>
        <p class="mt-1 text-sm text-slate-500">Materials stay private until you choose to share them. Students receive shared materials after the lesson.</p>
        <form action="{{ route('admin.lessons.materials.store', $booking) }}" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-5 sm:grid-cols-2" x-data="{ kind: {{ \Illuminate\Support\Js::from(old('kind', 'private_file')) }} }">
            @csrf
            <div><label for="material-kind" class="block text-sm font-semibold text-slate-700">Material type</label><select id="material-kind" name="kind" x-model="kind" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"><option value="private_file">Private PDF</option><option value="resource">Resource Library item</option><option value="external_link">Learning link</option><option value="recording">Recording link</option></select></div>
            <div><label for="material-title" class="block text-sm font-semibold text-slate-700">Title</label><input id="material-title" name="title" value="{{ old('title') }}" maxlength="200" required class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"></div>
            <div class="sm:col-span-2"><label for="material-description" class="block text-sm font-semibold text-slate-700">Description (optional)</label><textarea id="material-description" name="description" maxlength="5000" rows="2" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2">{{ old('description') }}</textarea></div>
            <div class="sm:col-span-2" x-show="kind === 'private_file'"><label for="material-file" class="block text-sm font-semibold text-slate-700">PDF file (up to 10 MB)</label><input id="material-file" type="file" name="file" accept=".pdf,application/pdf" :disabled="kind !== 'private_file'" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 p-2"><p class="mt-1 text-xs text-slate-500">PDFs are checked for obvious scripts, automatic actions and embedded files.</p></div>
            <div class="sm:col-span-2" x-show="kind === 'resource'" x-cloak><label for="material-resource" class="block text-sm font-semibold text-slate-700">Published resource</label><select id="material-resource" name="resource_id" :disabled="kind !== 'resource'" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"><option value="">Choose a resource</option>@foreach($resources as $resource)<option value="{{ $resource->id }}" @selected((string)old('resource_id') === (string)$resource->id)>{{ $resource->title }}</option>@endforeach</select></div>
            <div class="sm:col-span-2" x-show="kind === 'external_link' || kind === 'recording'" x-cloak><label for="material-url" class="block text-sm font-semibold text-slate-700">HTTPS link</label><input id="material-url" type="url" name="url" value="{{ old('url') }}" maxlength="2048" :disabled="kind !== 'external_link' && kind !== 'recording'" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"><p class="mt-1 text-xs text-slate-500">The link provider controls access after a link is shared. Check its sharing settings before publishing.</p></div>
            <div><label for="material-order" class="block text-sm font-semibold text-slate-700">Display order</label><input id="material-order" type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="10000" required class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"></div>
            <div class="flex min-h-11 items-center gap-3 self-end"><input type="hidden" name="student_visible" value="0"><input id="material-visible" type="checkbox" name="student_visible" value="1" @checked(old('student_visible')) class="rounded border-slate-300 text-amber-600"><label for="material-visible" class="text-sm font-semibold text-slate-700">Share with student</label></div>
            <div class="sm:col-span-2"><button class="min-h-11 rounded-xl bg-amber-600 px-5 py-3 font-semibold text-white hover:bg-amber-700">Attach material</button></div>
        </form>
    </section>
    <section aria-label="Attached materials" class="space-y-4">
        <h2 class="text-xl font-bold text-slate-900">Attached materials</h2>
        @forelse($booking->lessonMaterials->whereNull('withdrawn_at') as $material)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="break-words font-bold">{{ $material->title }}</h3><p class="mt-1 text-sm text-slate-500">{{ str_replace('_', ' ', $material->kind) }} · {{ $material->student_visible ? 'Shared with student after lesson' : 'Private preparation' }}@if($material->kind === 'resource') · {{ $material->resource?->title ?? 'Resource unavailable' }}@endif</p></div><a href="{{ route('admin.lessons.materials.open', [$booking, $material]) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center font-semibold text-amber-700 underline">Open material</a></div>
            <form action="{{ route('admin.lessons.materials.update', [$booking, $material]) }}" method="POST" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf @method('PATCH')
                <div><label for="title-{{ $material->id }}" class="text-sm font-semibold">Title</label><input id="title-{{ $material->id }}" name="title" value="{{ $material->title }}" maxlength="200" required class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"></div>
                <div><label for="order-{{ $material->id }}" class="text-sm font-semibold">Display order</label><input id="order-{{ $material->id }}" type="number" name="sort_order" value="{{ $material->sort_order }}" min="0" max="10000" required class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"></div>
                <div class="sm:col-span-2"><label for="description-{{ $material->id }}" class="text-sm font-semibold">Description</label><textarea id="description-{{ $material->id }}" name="description" rows="2" maxlength="5000" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2">{{ $material->description }}</textarea></div>
                <div class="flex min-h-11 items-center gap-3"><input type="hidden" name="student_visible" value="0"><input id="visible-{{ $material->id }}" type="checkbox" name="student_visible" value="1" @checked($material->student_visible) class="rounded border-slate-300 text-amber-600"><label for="visible-{{ $material->id }}" class="text-sm font-semibold">Share with student</label></div>
                <button class="min-h-11 rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white sm:justify-self-end">Save material</button>
            </form>
            <form action="{{ route('admin.lessons.materials.destroy', [$booking, $material]) }}" method="POST" class="mt-4 border-t border-slate-100 pt-3">@csrf @method('DELETE')<button class="min-h-11 rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700">Withdraw material</button></form>
        </article>
        @empty
        <p class="rounded-2xl border border-slate-200 bg-white p-5 text-slate-500">No materials attached yet.</p>
        @endforelse
        @foreach($booking->lessonMaterials->whereNotNull('withdrawn_at')->filter(fn ($item) => $item->path !== null) as $material)
        <div class="rounded-xl bg-amber-50 p-4"><p class="text-sm text-amber-900">Withdrawn PDF #{{ $material->id }} — file cleanup pending.</p><form action="{{ route('admin.lessons.materials.destroy', [$booking, $material]) }}" method="POST">@csrf @method('DELETE')<button class="mt-2 min-h-11 rounded-xl border border-amber-300 px-4 text-sm font-semibold text-amber-900">Retry file cleanup</button></form></div>
        @endforeach
    </section>
</div>
@endsection
