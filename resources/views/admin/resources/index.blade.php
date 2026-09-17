@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Learning Resources Library</h1>
            <p class="text-sm text-slate-500 mt-1">Manage downloadable Egyptian Arabic workbooks, vocabulary sheets, and gated learning materials.</p>
        </div>
        <a href="{{ route('admin.resources.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
            + Add New Resource
        </a>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3.5 font-semibold">Title & Slug</th>
                        <th class="px-4 py-3.5 font-semibold">Category</th>
                        <th class="px-4 py-3.5 font-semibold">Gate Type</th>
                        <th class="px-4 py-3.5 font-semibold">Downloads</th>
                        <th class="px-4 py-3.5 font-semibold">Status</th>
                        <th class="px-4 py-3.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($resources as $res)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-900">{{ $res->title }}</div>
                                <div class="text-xs text-slate-400 font-mono">/resources/{{ $res->slug }}</div>
                            </td>
                            <td class="px-4 py-4 text-xs font-medium text-slate-700">
                                {{ $res->category->name ?? 'General' }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold {{ $res->is_gated ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $res->is_gated ? 'Email Gated' : 'Direct Public' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-600">
                                <span class="font-bold text-slate-900">{{ $res->downloads_count }}</span> downloads
                                <span class="text-slate-400">({{ $res->requests_count }} requests)</span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold capitalize
                                    {{ $res->status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $res->status }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('resources.show', $res->slug) }}" target="_blank" class="text-xs font-semibold text-slate-500 hover:text-slate-800">
                                        View
                                    </a>
                                    <a href="{{ route('admin.resources.edit', $res->id) }}" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.resources.destroy', $res->id) }}" method="POST" onsubmit="return confirm('Archive this resource?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-800">
                                            Archive
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                <p class="text-sm font-semibold text-slate-600">No resources created yet.</p>
                                <p class="text-xs text-slate-400 mt-1">Add your first Egyptian Arabic learning workbook or PDF guide above.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($resources->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                {{ $resources->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
