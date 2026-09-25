@extends('layouts.admin')

@section('content')
@php
    $editing = $article !== null;
    $action = $editing ? route('admin.articles.update', $article) : route('admin.articles.store');
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold font-serif text-slate-900">{{ $editing ? 'Edit Blog Post' : 'Add Blog Post' }}</h1><p class="mt-1 text-sm text-slate-500">Blog post HTML is cleaned before it is stored.</p></div>
        <a href="{{ route('admin.articles.index') }}" class="text-sm font-semibold text-slate-600 underline">Back to Blogs</a>
    </div>
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('success'))<p role="status" class="rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</p>@endif
    <form method="POST" action="{{ $action }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
        @csrf @if($editing)@method('PUT')<input type="hidden" name="lock_version" value="{{ old('lock_version', $article->lock_version) }}">@else<input type="hidden" name="lock_version" value="1">@endif
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="title" class="block text-sm font-semibold">Title</label><input id="title" name="title" required maxlength="255" value="{{ old('title', $article?->title) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
            <div><label for="slug" class="block text-sm font-semibold">Slug</label><input id="slug" name="slug" required maxlength="255" value="{{ old('slug', $article?->slug) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
            <div><label for="locale" class="block text-sm font-semibold">Locale</label><input id="locale" name="locale" required maxlength="8" value="{{ old('locale', $article?->locale ?? 'en') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
            <div><label for="translation_group_id" class="block text-sm font-semibold">Translation group UUID (optional)</label><input id="translation_group_id" name="translation_group_id" value="{{ old('translation_group_id', $article?->translation_group_id) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        </div>
        <div><label for="excerpt" class="block text-sm font-semibold">Excerpt</label><textarea id="excerpt" name="excerpt" rows="3" required maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('excerpt', $article?->excerpt) }}</textarea></div>
        <div><label for="body" class="block text-sm font-semibold">Blog Post HTML</label><textarea id="body" name="body" rows="18" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">{{ old('body', $article?->body) }}</textarea><p class="mt-1 text-xs text-slate-500">Allowed: headings, paragraphs, emphasis, lists, quotes, links, and same-origin stored images.</p></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="featured_image_path" class="block text-sm font-semibold">Featured image path from media library</label><input id="featured_image_path" name="featured_image_path" value="{{ old('featured_image_path', $article?->featured_image_path) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
            <div><label for="status" class="block text-sm font-semibold">Status</label><select id="status" name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">@foreach(['draft', 'published', 'archived'] as $status)<option value="{{ $status }}" @selected(old('status', $article?->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div><label for="seo_title" class="block text-sm font-semibold">SEO title</label><input id="seo_title" name="seo_title" maxlength="255" value="{{ old('seo_title', $article?->seo_title) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
            <div><label for="seo_description" class="block text-sm font-semibold">SEO description</label><textarea id="seo_description" name="seo_description" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('seo_description', $article?->seo_description) }}</textarea></div>
        </div>
        <div><label for="canonical_url" class="block text-sm font-semibold">Canonical URL (optional)</label><input id="canonical_url" name="canonical_url" type="url" value="{{ old('canonical_url', $article?->canonical_url) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">
            @if($editing)<a href="{{ route('admin.articles.preview', $article) }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Preview</a>@endif
            <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2.5 font-semibold text-white hover:bg-amber-700">{{ $editing ? 'Save Blog Post' : 'Create Blog Post' }}</button>
        </div>
    </form>
</div>
@endsection
