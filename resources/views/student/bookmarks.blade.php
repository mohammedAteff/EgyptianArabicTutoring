@extends('layouts.student', ['title' => 'Bookmarks'])
@section('content')
<span data-learning-notes-listing hidden></span>
<h1 class="text-3xl font-semibold">{{ __('Bookmarks') }}</h1><p class="mt-2 text-stone-600">{{ __('Your saved notes, homework, and assigned resources.') }}</p>
@forelse($items as $item)
<article class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white p-5"><a href="{{ $item['url'] }}" class="font-semibold text-nile-800 underline">{{ $item['title'] }}</a><form method="POST" action="{{ route('student.bookmarks.destroy', $item['bookmark']) }}">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-stone-600">{{ __('Remove Bookmark') }}</button></form></article>
@empty<p class="mt-4 rounded-xl bg-white p-5">{{ __('No saved bookmarks yet. Save a note, homework item, or assigned resource to find it here.') }}</p>@endforelse
@endsection
