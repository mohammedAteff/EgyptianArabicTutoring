@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Interactive Arabic Games</h1>
            <p class="text-sm text-slate-500 mt-1">Configure student learning games, status availability, and vocabulary challenges.</p>
        </div>
        <a href="{{ route('admin.games.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>New Game</span>
        </a>
    </div>

    <!-- Games Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3.5 font-semibold">Game Title</th>
                        <th class="px-4 py-3.5 font-semibold">Slug (Path)</th>
                        <th class="px-4 py-3.5 font-semibold">Sort Order</th>
                        <th class="px-4 py-3.5 font-semibold">Status</th>
                        <th class="px-4 py-3.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($games as $game)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-900">{{ $game->title }}</div>
                                <div class="text-xs text-slate-500 mt-0.5 line-clamp-1">{{ $game->description }}</div>
                            </td>
                            <td class="px-4 py-4 font-mono text-xs text-slate-600">
                                /games/{{ $game->slug }}
                            </td>
                            <td class="px-4 py-4 text-xs font-bold text-slate-700">
                                {{ $game->sort_order }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold capitalize
                                    {{ $game->status === 'available' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $game->status === 'coming_soon' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $game->status === 'disabled' || $game->status === 'archived' ? 'bg-slate-100 text-slate-600' : '' }}
                                    {{ $game->status === 'draft' ? 'bg-blue-100 text-blue-800' : '' }}">
                                    {{ str_replace('_', ' ', $game->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ $game->target_url ?? route('games.show', $game->slug) }}" target="_blank" class="text-xs font-semibold text-slate-500 hover:text-slate-800">
                                        Play Test
                                    </a>
                                    <a href="{{ route('admin.games.edit', $game->id) }}" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.games.destroy', $game->id) }}" method="POST" onsubmit="return confirm('Delete game {{ $game->title }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-500 hover:text-red-700 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-slate-400">
                                No educational games found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
