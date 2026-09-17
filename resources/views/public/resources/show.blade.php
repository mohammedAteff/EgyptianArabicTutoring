@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs font-medium text-stone-500 mb-8">
        <a href="{{ route('home') }}" class="hover:text-stone-800">Home</a>
        <span>/</span>
        <a href="{{ route('resources.index') }}" class="hover:text-stone-800">Resources</a>
        <span>/</span>
        <span class="text-stone-800 font-bold truncate">{{ $resource->title }}</span>
    </nav>

    @if(session('success'))
        <div class="mb-8 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-2xl text-sm text-emerald-800 font-medium shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
        <!-- Main Description (7 cols) -->
        <div class="lg:col-span-7 space-y-6">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-terracotta-600 bg-terracotta-50 px-3 py-1 rounded-full">
                    {{ $resource->category?->name ?? 'Free Resource' }}
                </span>
                <span class="text-xs font-semibold text-stone-500 bg-stone-100 px-2.5 py-0.5 rounded-full">
                    {{ strtoupper($resource->file_type ?? 'PDF') }}
                </span>
            </div>

            @if($resource->cover_image_path)
                <div class="rounded-3xl overflow-hidden shadow-sm border border-stone-200/80 mb-6">
                    <img src="{{ str_starts_with($resource->cover_image_path, 'http') ? $resource->cover_image_path : \Illuminate\Support\Facades\Storage::disk('public')->url($resource->cover_image_path) }}"
                         alt="{{ $resource->title }}" class="w-full max-h-96 object-cover">
                </div>
            @endif

            <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-900 tracking-tight leading-snug">
                {{ $resource->title }}
            </h1>

            <div class="prose prose-stone text-stone-600 leading-relaxed text-base space-y-4">
                <p class="font-medium text-stone-800 text-lg leading-relaxed">
                    {{ $resource->short_description }}
                </p>
                <div class="pt-2">
                    {!! nl2br(e($resource->full_description ?? $resource->short_description)) !!}
                </div>
            </div>

            <!-- What's inside box -->
            <div class="p-6 rounded-2xl bg-stone-50 border border-stone-200/80 space-y-3">
                <h3 class="font-bold text-stone-900 text-sm">Included in this Guide:</h3>
                <ul class="space-y-2 text-xs sm:text-sm text-stone-600">
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Authentic Egyptian colloquial Arabic (Amiya) phonetic spelling & Arabic script</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Real-world conversational contexts & cultural nuances</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Printable and mobile-friendly reference sheet</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Download Gate Card (5 cols) -->
        <div class="lg:col-span-5">
            <div class="bg-white rounded-3xl border border-stone-200/90 p-6 sm:p-8 shadow-xl sticky top-28">
                @if(! $resource->is_gated || session('access_granted'))
                    <!-- Download State -->
                    <div class="text-center space-y-4 py-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-stone-900">Your File Is Ready!</h2>
                        <p class="text-xs text-stone-500 leading-relaxed">
                            Click below to save the file directly to your device.
                        </p>
                        <div class="pt-2">
                            <a href="{{ route('resources.download', array_filter(['slug' => $resource->slug, 'token' => session('download_token')])) }}"
                               class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-base px-6 py-4 rounded-full shadow-md hover:shadow-lg transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                <span>Download {{ strtoupper($resource->file_type ?? 'PDF') }}</span>
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Email Gate Form -->
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Free Instant Access</span>
                        </div>
                        <h2 class="text-xl font-bold text-stone-900">Get This Resource</h2>
                        <p class="text-stone-500 text-xs mt-1 mb-6 leading-relaxed">
                            Instant access provided right on this screen. No waiting for email links or promotional spam.
                        </p>

                        <form method="POST" action="{{ route('resources.request', $resource->slug) }}" class="space-y-4">
                            @csrf

                            <div>
                                <label for="name" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-1">
                                    Your Name <span class="text-stone-400 font-normal">(Optional)</span>
                                </label>
                                <input type="text"
                                       name="name"
                                       id="name"
                                       value="{{ old('name') }}"
                                       placeholder="e.g. David"
                                       class="w-full px-4 py-2.5 rounded-xl border border-stone-200 text-sm focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-100 outline-none">
                            </div>

                            <div>
                                <label for="email" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-1">
                                    Email Address <span class="text-rose-500">*</span>
                                </label>
                                <input type="email"
                                       name="email"
                                       id="email"
                                       required
                                       value="{{ old('email') }}"
                                       placeholder="david@example.com"
                                       class="w-full px-4 py-2.5 rounded-xl border border-stone-200 text-sm focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-100 outline-none @error('email') border-rose-400 @enderror">
                                @error('email')
                                    <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="pt-2">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-sm px-6 py-3.5 rounded-full shadow-md hover:shadow transition-all">
                                    <span>Get Free Resource Now</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </button>
                            </div>

                            <p class="text-[11px] text-stone-400 text-center leading-tight">
                                We respect your privacy. Resource access does not trigger unsolicited marketing emails.
                            </p>
                        </form>
                    </div>
                @endif

                <!-- Cross-promote Lesson -->
                <div class="mt-8 pt-6 border-t border-stone-100 text-center">
                    <div class="text-xs font-bold text-stone-900 mb-1">Want to practice speaking?</div>
                    <p class="text-xs text-stone-500 mb-3">Practice these exact phrases in a private 1-on-1 session.</p>
                    <a href="{{ route('booking.index') }}" class="text-xs font-bold text-terracotta-600 hover:text-terracotta-700 underline">
                        Book a Lesson with Ahmad →
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
