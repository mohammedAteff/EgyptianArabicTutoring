@extends('layouts.admin')

@section('content')
<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Offers & Promotions Portal</h1>
            <p class="text-sm text-slate-500 mt-1">Manage public promotional banners, modals, and inline announcement cards with Cairo-scheduled time windows.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.promotions.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Create Promotion</span>
            </a>
        </div>
    </div>

    <!-- Promotions List -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
        @if($promotions->isEmpty())
            <div class="p-12 text-center space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">No promotions configured</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">Create a promotional campaign banner or modal to notify visitors of limited-time discounts or announcements.</p>
                <div class="pt-2">
                    <a href="{{ route('admin.promotions.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors">
                        <span>New Promotion</span>
                    </a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-6 py-3.5">Campaign & Headline</th>
                            <th class="px-4 py-3.5">Format</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-4 py-3.5">Cairo Schedule</th>
                            <th class="px-4 py-3.5">Timer</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($promotions as $promo)
                            @php
                                $startsCairo = $promo->starts_at ? $promotionService->utcToCairo($promo->starts_at) : null;
                                $endsCairo = $promo->ends_at ? $promotionService->utcToCairo($promo->ends_at) : null;
                                $isLive = $promo->isCurrentlyActive();
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        @if($promo->banner_image_path)
                                            <img src="{{ asset('storage/' . $promo->banner_image_path) }}" alt="" class="w-12 h-12 rounded-xl object-cover border border-slate-200 shrink-0">
                                        @else
                                            <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0 text-slate-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 text-sm truncate">{{ $promo->title }}</div>
                                            <div class="text-slate-600 truncate mt-0.5">{{ $promo->headline }}</div>
                                            <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-2">
                                                <span>CTA: <strong class="text-slate-600">{{ $promo->cta_text }}</strong> &rarr; <span class="font-mono text-slate-500">{{ $promo->cta_url }}</span></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @if($promo->display_type === 'top_bar')
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">Top Bar</span>
                                    @elseif($promo->display_type === 'floating_modal')
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">Modal Popup</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">Inline Card</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @if($isLive)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Live Public
                                        </span>
                                    @elseif(! $promo->is_active)
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">Disabled</span>
                                    @elseif($promo->starts_at && $promo->starts_at->isFuture())
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">Scheduled</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-100 text-red-800 border border-red-200">Expired</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-slate-500 font-mono text-[11px]">
                                    <div>From: {{ $startsCairo ? $startsCairo->format('Y-m-d H:i') : 'Immediate' }}</div>
                                    <div class="mt-0.5">Until: {{ $endsCairo ? $endsCairo->format('Y-m-d H:i') : 'Indefinite' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @if($promo->has_countdown)
                                        <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold text-[11px]">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Countdown
                                        </span>
                                    @else
                                        <span class="text-slate-400">None</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                    <a href="{{ route('admin.promotions.preview', $promo) }}" target="_blank"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition-colors">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <span>Preview</span>
                                    </a>
                                    <a href="{{ route('admin.promotions.edit', $promo) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg text-xs font-semibold transition-colors">
                                        <span>Edit</span>
                                    </a>
                                    <form action="{{ route('admin.promotions.toggle', $promo) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $promo->is_active ? 'bg-slate-100 hover:bg-slate-200 text-slate-600' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' }} transition-colors">
                                            {{ $promo->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.promotions.destroy', $promo) }}" method="POST" class="inline" onsubmit="return confirm('Permanently delete this promotion?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg text-xs font-semibold transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($promotions->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $promotions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
