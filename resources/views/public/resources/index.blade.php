@extends('layouts.public')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Header -->
    <div class="text-center max-w-3xl mx-auto mb-12">
        <span class="inline-flex items-center gap-1.5 bg-terracotta-50 text-terracotta-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-3">
            📚 {{ __('Free Learning Library') }}
        </span>
        <h1 class="text-3xl sm:text-5xl font-extrabold text-stone-900 tracking-tight">
            {{ __('Egyptian Arabic Workbooks & Guides') }}
        </h1>
        <p class="text-stone-600 text-base sm:text-lg mt-3 leading-relaxed">
            {{ __('Curated vocabulary cheat-sheets, conversation frameworks, and pronunciation guides prepared directly by Abdallah.') }}
        </p>
    </div>

    <!-- Category Filter Pills -->
    <div class="flex flex-wrap items-center justify-center gap-2 mb-12">
        <a href="{{ localized_url('resources') }}"
           class="px-4 py-2 rounded-full text-xs font-bold transition-all {{ empty($selectedCategory) ? 'bg-terracotta-500 text-white shadow-sm' : 'bg-white border border-stone-200 text-stone-700 hover:bg-stone-50' }}">
            {{ __('All Resources') }}
        </a>
        @foreach($categories as $cat)
            <a href="{{ localized_url('resources') . '?category=' . $cat->slug }}"
               class="px-4 py-2 rounded-full text-xs font-bold transition-all {{ $selectedCategory === $cat->slug ? 'bg-terracotta-500 text-white shadow-sm' : 'bg-white border border-stone-200 text-stone-700 hover:bg-stone-50' }}">
                {{ $cat->liveTranslation()?->name ?? $cat->name }}
            </a>
        @endforeach
    </div>

    <!-- Resource Cards Grid -->
    @if($resources->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            @foreach($resources as $resource)
                <div class="bg-white rounded-3xl border border-stone-200/80 overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col justify-between group">
                    <div>
                        <!-- Cover / Header Banner -->
                        <div class="h-44 bg-gradient-to-br from-stone-100 to-terracotta-50/50 p-6 flex flex-col justify-between border-b border-stone-100 relative overflow-hidden">
                            @if($resource->cover_image_path)
                                <img src="{{ str_starts_with($resource->cover_image_path, 'http') ? $resource->cover_image_path : \Illuminate\Support\Facades\Storage::disk('public')->url($resource->cover_image_path) }}"
                                     alt="{{ $resource->liveTranslation()?->title ?? $resource->title }}" class="absolute inset-0 w-full h-full object-cover">
                                <div class="absolute inset-0 bg-stone-900/30"></div>
                            @endif
                            <div class="flex items-center justify-between relative z-10">
                                <span class="text-xs font-bold uppercase tracking-wider text-terracotta-600 bg-white px-2.5 py-1 rounded-full shadow-xs">
                                    {{ $resource->category?->liveTranslation()?->name ?? $resource->category?->name ?? 'Guide' }}
                                </span>
                                <span class="text-xs font-semibold text-stone-500 bg-white/80 px-2 py-0.5 rounded-full">
                                    {{ strtoupper($resource->file_type ?? 'PDF') }}
                                </span>
                            </div>
                            @if(!$resource->cover_image_path)
                                <div class="text-3xl font-bold font-cairo text-stone-300 group-hover:text-terracotta-400 transition-colors">
                                    كتاب
                                </div>
                            @endif
                        </div>

                        <!-- Body -->
                        <div class="p-6">
                            <h2 class="text-xl font-bold text-stone-900 group-hover:text-terracotta-600 transition-colors leading-snug">
                                <a href="{{ localized_url('resource.detail', $resource->slug) }}">
                                    {{ $resource->liveTranslation()?->title ?? $resource->title }}
                                </a>
                            </h2>
                            <p class="text-stone-600 text-sm mt-2 line-clamp-3 leading-relaxed">
                                {{ $resource->liveTranslation()?->short_description ?? $resource->short_description }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 pb-6 pt-2 flex items-center justify-between border-t border-stone-100 text-xs">
                        <span class="text-emerald-600 font-semibold flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            {{ __('Free Download') }}
                        </span>
                        <a href="{{ localized_url('resource.detail', $resource->slug) }}"
                           class="font-bold text-terracotta-600 hover:text-terracotta-700 inline-flex items-center gap-1 group-hover:translate-x-0.5 transition-transform">
                            <span>{{ __('Request Free Access') }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $resources->links() }}
        </div>
    @else
        <div class="bg-white rounded-3xl border border-stone-200 p-12 text-center max-w-md mx-auto">
            <div class="text-4xl mb-3">📖</div>
            <h3 class="text-lg font-bold text-stone-900">{{ __('No resources found in this category') }}</h3>
            <p class="text-stone-500 text-sm mt-1">{{ __('Check back soon or browse all available guides.') }}</p>
            <div class="mt-6">
                <a href="{{ localized_url('resources') }}" class="text-xs font-semibold text-terracotta-600 bg-terracotta-50 px-4 py-2 rounded-full">
                    {{ __('All Resources') }}
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
