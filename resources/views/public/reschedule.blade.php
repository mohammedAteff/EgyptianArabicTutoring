@extends('layouts.public')

@section('content')
<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-12 shadow-sm text-center">
        <div class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-700" aria-hidden="true">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V5a4 4 0 0 1 8 0v2m-9 0h10a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm4 4v4m0 0h.01" />
            </svg>
        </div>

        <h1 class="text-3xl font-extrabold text-stone-900 tracking-tight">Rescheduling by direct contact</h1>
        <p class="mt-4 text-stone-600 leading-relaxed">
            To protect calendar availability and booking history, lesson changes are handled by Abdallah directly.
            Please contact him by WhatsApp, Telegram, or email at least 24 hours before your lesson.
        </p>

        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ url()->previous() }}"
               class="w-full sm:w-auto inline-flex items-center justify-center rounded-full bg-stone-900 px-6 py-3 text-sm font-bold text-white hover:bg-stone-800 transition-colors">
                Back to confirmation
            </a>
            <a href="{{ url('/') }}#contact"
               class="w-full sm:w-auto inline-flex items-center justify-center rounded-full border border-stone-300 px-6 py-3 text-sm font-bold text-stone-700 hover:bg-stone-50 transition-colors">
                Contact Abdallah
            </a>
        </div>
    </div>
</main>
@endsection
