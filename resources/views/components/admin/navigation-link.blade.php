@props(['item', 'unreadCount' => 0])
<a href="{{ route($item['destination']) }}"
   @if($item['current']) aria-current="page" @endif
   @class(['flex min-h-11 items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400',
       'bg-amber-600 font-semibold text-white' => $item['current'] && !($item['danger'] ?? false),
       'bg-red-700 font-semibold text-white' => $item['current'] && ($item['danger'] ?? false),
       'text-slate-300 hover:bg-slate-800 hover:text-white' => !$item['current'] && !($item['danger'] ?? false),
       'text-red-200 hover:bg-slate-800' => !$item['current'] && ($item['danger'] ?? false)])>
    <svg aria-hidden="true" class="h-4 w-4 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
    <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
    @if(($item['badge'] ?? false) && $unreadCount > 0)
        <span aria-label="{{ $unreadCount }} unread notifications" class="rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-slate-950">{{ $unreadCount }}</span>
    @endif
</a>
