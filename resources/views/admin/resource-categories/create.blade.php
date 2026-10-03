@extends('layouts.admin')

@section('content')
<x-resources-tabs />
<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Create Resource Category</h1>
            <p class="text-sm text-slate-500 mt-1">Add a new structured taxonomy for learning materials.</p>
        </div>
        <a href="{{ route('admin.resource-categories.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
            &larr; Back to Categories
        </a>
    </div>

    <!-- Form -->
    <form action="{{ route('admin.resource-categories.store') }}" method="POST" class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 sm:p-8 space-y-6">
        @csrf

        <!-- Name -->
        <div>
            <label for="name" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Category Name *</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="e.g. Grammar Workbooks"
                   class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all">
            @error('name')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <!-- Slug -->
        <div>
            <label for="slug" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">URL Slug (optional, auto-generated if empty)</label>
            <input type="text" name="slug" id="slug" value="{{ old('slug') }}" placeholder="grammar-workbooks"
                   class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white font-mono transition-all">
            @error('slug')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Sort Order -->
            <div>
                <label for="sort_order" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Sort Order</label>
                <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                       class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white font-mono transition-all">
                @error('sort_order')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Active Status -->
            <div class="flex items-center pt-6">
                <label class="relative flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="active" value="1" {{ old('active', '1') ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                    <span class="text-sm font-semibold text-slate-700">Active (visible to students)</span>
                </label>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="{{ route('admin.resource-categories.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-semibold transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold transition-colors shadow-xs font-serif">
                Create Category
            </button>
        </div>
    </form>

</div>
@endsection
