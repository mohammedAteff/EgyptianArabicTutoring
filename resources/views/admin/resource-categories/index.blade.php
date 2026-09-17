@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Resource Categories</h1>
            <p class="text-sm text-slate-500 mt-1">Organize student learning materials into structured categories.</p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <a href="{{ route('admin.resources.index') }}" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors">
                Back to Resources
            </a>
            <a href="{{ route('admin.resource-categories.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Category</span>
            </a>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5 font-semibold">Name</th>
                        <th class="px-6 py-3.5 font-semibold">Slug</th>
                        <th class="px-6 py-3.5 font-semibold text-center">Resources Count</th>
                        <th class="px-6 py-3.5 font-semibold text-center">Sort Order</th>
                        <th class="px-6 py-3.5 font-semibold text-center">Status</th>
                        <th class="px-6 py-3.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($categories as $category)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $category->name }}</div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-500">
                                {{ $category->slug }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $category->resources_count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $category->resources_count }} {{ Str::plural('resource', $category->resources_count) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-mono text-xs text-slate-600">
                                {{ $category->sort_order }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($category->active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Disabled</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.resource-categories.edit', $category) }}" class="text-xs font-semibold text-amber-600 hover:text-amber-700 transition-colors">
                                        Edit
                                    </a>
                                    @if($category->resources_count === 0)
                                        <form action="{{ route('admin.resource-categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete category {{ $category->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-500 hover:text-red-700 transition-colors">
                                                Delete
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-300 cursor-not-allowed" title="Cannot delete: contains active resources">
                                            Delete
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                No categories defined yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
