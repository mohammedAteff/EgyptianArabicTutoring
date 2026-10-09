@extends('layouts.public', ['noIndex' => true])
@section('content')
<section lang="en" class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
    <h1 class="font-serif text-4xl font-semibold text-nile-900">{{ ($translationUnavailable ?? false) ? 'Translation Unavailable' : "We Couldn't Find That Article." }}</h1>
    <p class="mt-6 leading-8 text-stone-600">{{ ($translationUnavailable ?? false) ? "This article hasn't been published in the selected language yet." : 'The article may have been moved, unpublished, or removed. You can return to the blog to explore other available articles.' }}</p>
    @if($versions->isNotEmpty())<nav aria-label="Available published versions" class="mt-6 flex flex-wrap gap-5">@foreach($versions as $version)<a href="{{ $version->publicUrl() }}" class="font-semibold text-terracotta-600 underline">Read in {{ ['en'=>'English','fr'=>'Français','de'=>'Deutsch'][$version->locale] }}</a>@endforeach</nav>@endif
    <a href="{{ localized_url('blogs') }}" class="mt-8 inline-block rounded-xl bg-terracotta-600 px-5 py-3 font-semibold text-white">Browse Articles</a>
</section>
@endsection
