@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-12 shadow-sm space-y-6">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-900 tracking-tight">
            {{ $page->title }}
        </h1>

        <div class="prose prose-stone max-w-none text-stone-700 leading-relaxed text-base sm:text-lg">
            {!! $page->content !!}
        </div>
    </div>
</div>
@endsection
