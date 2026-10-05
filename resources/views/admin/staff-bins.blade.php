@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <h1 class="font-serif text-3xl font-bold">Staff Notes</h1><p class="text-sm text-slate-500">Shared text for staff collaboration. Only the author can edit; only Super Admin can delete.</p>
    @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</p>@endif
    @if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-800">{{ $errors->first() }}</p>@endif
    <x-report-filters :filters="$filters" :action="route('admin.staff-bins.index')" :fields="['q'=>['Search','search'], 'collection'=>['Collection',['shared'=>'Shared Pins','mine'=>'My Pins','favorites'=>'My Favorites']], 'owner'=>['Owner',['mine'=>'My notes','all'=>'All staff']], 'sort'=>['Sort',['newest'=>'Recently updated','oldest'=>'Oldest updated','title'=>'Title']]]" />
    @if(!$editing)<x-staff-saved-views section="staff_notes" :filters="$filters" :views="$savedViews" />@endif
    <form method="POST" action="{{ $editing ? route('admin.staff-bins.update',$editing) : route('admin.staff-bins.store') }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">@csrf @if($editing) @method('PUT') @endif
        <h2 class="text-lg font-semibold">{{ $editing ? 'Edit note' : 'Create staff note' }}</h2>
        <label class="block text-sm font-semibold">Title<input name="title" required maxlength="160" value="{{ old('title',$editing?->title) }}" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></label>
        <label class="block text-sm font-semibold">Text<textarea name="body" required maxlength="30000" rows="7" class="mt-1 w-full rounded-xl border border-slate-300 p-3">{{ old('body',$editing?->body) }}</textarea></label>
        <button class="rounded-xl bg-amber-600 px-5 py-2.5 font-semibold text-white">Save note</button>@if($editing)<a href="{{ route('admin.staff-bins.index') }}" class="ml-3 text-sm">Cancel</a>@endif
    </form>
    @forelse($bins as $bin)<article data-bin class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><h2 class="break-words text-lg font-semibold">{{ $bin->title }}</h2><x-copy-button :bin="true" label="Copy note text" /></div>
        @if($bin->pinned)<p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-900">Pinned for all staff · {{ $bin->pinnedBy?->name ?? 'Former staff member' }} · {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($bin->pinned_at) }}</p>@endif
        <p class="mt-1 text-xs text-slate-500">{{ $bin->author?->name ?? 'Former staff member' }} · Created {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($bin->created_at) }} · Updated {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($bin->updated_at) }}</p>
        <div data-bin-body class="mt-4 break-words whitespace-pre-wrap text-sm">{{ $bin->body }}</div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            @foreach(['pinned' => ['my_pinned', 'Pin only for me', 'Unpin for me'], 'favorite' => ['my_favorite', 'Add to favorites', 'Remove favorite']] as $flag => [$attribute, $add, $remove])
            <form method="POST" action="{{ route('admin.staff-bins.personal', $bin) }}">@csrf @method('PATCH')<input type="hidden" name="flag" value="{{ $flag }}"><input type="hidden" name="enabled" value="{{ $bin->{$attribute} ? '0' : '1' }}"><button class="min-h-11 rounded-lg bg-slate-100 px-3 text-sm font-semibold text-slate-700">{{ $bin->{$attribute} ? $remove : $add }}</button></form>
            @endforeach
            @can('sharedPin', $bin)<form method="POST" action="{{ route('admin.staff-bins.shared-pin', $bin) }}">@csrf @method('PATCH')<input type="hidden" name="pinned" value="{{ $bin->pinned ? '0' : '1' }}"><button class="min-h-11 rounded-lg bg-amber-50 px-3 text-sm font-semibold text-amber-800">{{ $bin->pinned ? 'Unpin for all staff' : 'Pin for all staff' }}</button></form>@endcan
            @can('update',$bin)<a href="{{ route('admin.staff-bins.edit',$bin) }}" class="py-3 text-sm font-semibold text-amber-800">Edit</a>@endcan
        @can('delete',$bin)<form method="POST" action="{{ route('admin.staff-bins.destroy',$bin) }}">@csrf @method('DELETE')<details class="text-sm"><summary class="cursor-pointer font-semibold text-rose-700">Remove note</summary><p class="mt-2 text-slate-600">Deletion history will be retained.</p><label class="mt-2 flex items-center gap-2"><input type="checkbox" name="confirmation" value="DELETE" required>Confirm removal</label><button class="mt-2 rounded-lg bg-rose-700 px-3 py-2 font-semibold text-white">Delete note</button></details></form>@endcan</div>
    </article>@empty @if(! $editing)<p class="rounded-2xl bg-white p-6 text-slate-500">No staff notes match your search.</p>@endif @endforelse
    @if(method_exists($bins,'links')){{ $bins->links() }}@endif
</div>
@endsection
