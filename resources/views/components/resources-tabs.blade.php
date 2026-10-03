<nav aria-label="Resources management" class="mb-6 flex flex-wrap gap-2">
    @foreach(['admin.resources.index' => 'Resources', 'admin.resource-categories.index' => 'Categories'] as $route => $label)
    <a href="{{ route($route) }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-amber-600 text-white' : 'border border-slate-200 bg-white text-slate-700' }}">{{ $label }}</a>
    @endforeach
</nav>
