@props(['groups', 'unreadCount' => 0, 'role'])
<div class="flex min-h-0 flex-1 flex-col text-sm font-medium">
    <nav aria-label="Daily shortcuts" class="shrink-0 space-y-2 border-b border-slate-800 px-3 py-3">
        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Quick Access</p>
        <div class="grid grid-cols-2 gap-2">
            @foreach($groups as $group)
                @foreach($group['items'] as $item)
                    @if(isset($item['shortcut']))
                        <a href="{{ route($item['destination']) }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}"
                           @class(['flex min-h-11 items-center justify-center rounded-lg border px-2 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400', 'border-amber-500 bg-amber-600 text-white' => $item['current'], 'border-slate-700 bg-slate-800 text-slate-200 hover:border-amber-500 hover:text-white' => !$item['current']])>{{ $item['shortcut'] }}</a>
                    @endif
                @endforeach
            @endforeach
        </div>
    </nav>
    <nav id="admin-sidebar-nav" aria-label="Staff navigation" data-navigation-role="{{ $role }}" class="flex-1 space-y-1 overflow-y-auto px-3 py-3">
    @foreach($groups as $key => $group)
        <details data-navigation-group="{{ $key }}" data-navigation-active="{{ $group['current'] ? 'true' : 'false' }}" @if($group['current']) open @endif class="group rounded-xl border border-transparent open:border-slate-700 open:bg-slate-950/40">
            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 [&::-webkit-details-marker]:hidden">
                <span class="min-w-0 flex-1">{{ $group['label'] }}</span>
                @if($key === 'daily' && $unreadCount > 0)
                    <span aria-label="{{ $unreadCount }} unread notifications" class="rounded-full bg-amber-500 px-1.5 py-0.5 text-xs font-bold text-slate-950">{{ $unreadCount }}</span>
                @endif
                <svg aria-hidden="true" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
            </summary>
            <div class="space-y-1 px-1 pb-2" id="admin-navigation-{{ $key }}">
                @foreach($group['items'] as $item)
                    <x-admin.navigation-link :item="$item" :unread-count="$unreadCount" />
                @endforeach
            </div>
        </details>
    @endforeach
    </nav>
</div>
