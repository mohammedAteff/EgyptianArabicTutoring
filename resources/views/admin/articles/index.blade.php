@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold font-serif text-slate-900">Blogs</h1><p class="mt-1 text-sm text-slate-500">Draft, publish, and maintain blog posts and revisions.</p></div>
        <a href="{{ route('admin.articles.create') }}" class="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Add Blog Post</a>
    </div>
    @if(session('success'))<p role="status" class="rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</p>@endif
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="divide-y divide-slate-100">
            @forelse($articles as $article)
                <a href="{{ route('admin.articles.edit', $article) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50">
                    <span><span class="block font-semibold text-slate-900">{{ $article->title }}</span><span class="mt-1 block text-xs text-slate-500">/articles/{{ $article->slug }} · {{ strtoupper($article->locale) }}</span></span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">{{ $article->status }}</span>
                </a>
            @empty
                <p class="px-5 py-8 text-sm text-slate-500">No blog posts yet.</p>
            @endforelse
        </div>
    </div>
    {{ $articles->links() }}
</div>
@endsection
