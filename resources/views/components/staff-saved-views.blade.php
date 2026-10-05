@props(['section', 'filters', 'views'])
<div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm">
    <span class="font-semibold text-slate-700">My saved views</span>
    @foreach($views as $savedView)
    <div class="flex items-center gap-1 rounded-lg bg-slate-100 pl-3">
        <a class="py-2 font-medium text-amber-800" href="{{ route('admin.saved-views.apply', $savedView->id) }}">{{ $savedView->name }}</a>
        <form method="POST" action="{{ route('admin.saved-views.destroy', $savedView->id) }}">@csrf @method('DELETE')<button class="min-h-11 min-w-11 rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-700" aria-label="Remove saved view {{ $savedView->name }}">×</button></form>
    </div>
    @endforeach
    <form method="POST" action="{{ route('admin.saved-views.store') }}" class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:flex-1">@csrf
        <input type="hidden" name="section" value="{{ $section }}">
        @foreach($filters as $key => $value) @if(in_array($key, \App\Domains\Administration\Services\StaffSavedViewService::KEYS[$section], true) && is_scalar($value))<input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">@endif @endforeach
        <label class="min-w-0 flex-1"><span class="sr-only">Saved view name</span><input name="name" required maxlength="80" placeholder="Name this view" class="min-h-11 w-full rounded-lg border border-slate-300 px-3"></label>
        <button class="min-h-11 rounded-lg bg-slate-900 px-4 font-semibold text-white">Save current view</button>
    </form>
</div>
