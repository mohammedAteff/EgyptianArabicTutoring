@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Notification Center</h1>
            <p class="text-sm text-slate-500 mt-1">Internal operations alerts, new bookings, resource leads, and system events.</p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <!-- Filter Switcher -->
            <div class="flex items-center bg-slate-200/80 p-1 rounded-xl">
                <a href="{{ route('admin.notifications.index', ['filter' => 'all']) }}" 
                   class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $filter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    All
                </a>
                <a href="{{ route('admin.notifications.index', ['filter' => 'unread']) }}" 
                   class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 {{ $filter === 'unread' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    <span>Unread</span>
                    @if($unreadCount > 0)
                        <span class="px-1.5 py-0.5 rounded-full bg-amber-600 text-white text-[10px] font-bold">{{ $unreadCount }}</span>
                    @endif
                </a>
            </div>

            @if($unreadCount > 0)
                <form action="{{ route('admin.notifications.read-all') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Notifications List -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 divide-y divide-slate-100 overflow-hidden">
        @forelse($notifications as $notification)
            @php
                $isUnread = !$notification->isRead();
                $level = $notification->level;
            @endphp
            <div class="p-4 sm:p-5 flex items-start gap-4 transition-colors {{ $isUnread ? 'bg-amber-50/30 hover:bg-amber-50/50' : 'hover:bg-slate-50/70' }}">
                
                <!-- Status Icon -->
                <div class="shrink-0 mt-0.5">
                    @if($level === 'success')
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    @elseif($level === 'warning')
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                    @elseif($level === 'danger')
                        <div class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    @else
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    @endif
                </div>

                <!-- Body -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-900 text-sm">{{ $notification->title }}</span>
                        @if($isUnread)
                            <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 text-[10px] font-bold uppercase tracking-wider">New</span>
                        @endif
                        <span class="text-xs text-slate-400 ml-auto">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>

                    <p class="text-sm text-slate-600 mt-1 leading-relaxed">{{ $notification->message }}</p>

                    @if(!empty($notification->data['repeat_count']) && $notification->data['repeat_count'] > 1)
                        <div class="mt-2 text-xs text-slate-400">
                            Occurred {{ $notification->data['repeat_count'] }} times (grouped).
                        </div>
                    @endif

                    <!-- Action buttons -->
                    <div class="flex items-center gap-3 mt-3">
                        @if($notification->link)
                            <a href="{{ $notification->link }}" class="text-xs font-semibold text-amber-600 hover:text-amber-700 transition-colors inline-flex items-center gap-1">
                                <span>View Details</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endif

                        @if($isUnread)
                            <form action="{{ route('admin.notifications.read', $notification) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-xs text-slate-500 hover:text-slate-800 transition-colors font-medium">
                                    Mark as read
                                </button>
                            </form>
                        @endif

                        <form action="{{ route('admin.notifications.destroy', $notification) }}" method="POST" onsubmit="return confirm('Dismiss this notification?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-slate-400 hover:text-red-600 transition-colors">
                                Dismiss
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900">No notifications found</h3>
                <p class="text-xs text-slate-500 mt-1">When new bookings, resource leads, or alerts occur, they will appear here.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif

</div>
@endsection
