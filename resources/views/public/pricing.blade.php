@extends('layouts.public')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <!-- Hero Header -->
    <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
        <span class="inline-flex items-center gap-1.5 bg-terracotta-50 text-terracotta-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
            {{ __('pricing.badge') }}
        </span>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-stone-900 tracking-tight">
            {{ __('pricing.title') }}
        </h1>
        <p class="text-stone-600 text-lg leading-relaxed">
            {{ __('pricing.subtitle') }}
        </p>
    </div>

    <!-- Diagnostic Credit Rule Banner -->
    <div class="mb-12 bg-amber-50/80 border border-amber-200/80 rounded-2xl p-6 shadow-sm">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="space-y-1">
                <h2 class="text-base font-bold text-amber-950">
                    {{ __('pricing.credit_rule_title') }}
                </h2>
                <p class="text-sm text-amber-900/90 leading-relaxed">
                    {{ __('pricing.credit_rule_desc') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Main Pricing Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch mb-16">
        <!-- 1. Diagnostic & Roadmap (Mandatory Entry) -->
        <div class="bg-white rounded-3xl border-2 border-stone-200 p-8 flex flex-col justify-between shadow-sm relative">
            <div>
                <div class="flex items-center justify-between gap-2 mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full">
                        {{ __('pricing.diagnostic_badge') }}
                    </span>
                    <span class="text-xs text-stone-500 font-medium font-mono">
                        {{ __('pricing.diagnostic_specs') }}
                    </span>
                </div>
                <h2 class="text-2xl font-extrabold text-stone-900 mb-2">
                    {{ __('pricing.diagnostic_title') }}
                </h2>
                <p class="text-stone-600 text-sm leading-relaxed mb-6">
                    {{ __('pricing.diagnostic_desc') }}
                </p>

                <div class="mb-6 pt-6 border-t border-stone-100">
                    <div class="flex items-baseline gap-2">
                <span class="text-4xl font-extrabold text-stone-900 font-mono">{{ __('pricing.diagnostic_price') }}</span>
                        <span class="text-xs text-stone-500 font-medium">{{ __('pricing.diagnostic_rate') }}</span>
                    </div>
                    <div class="text-xs text-emerald-600 font-semibold mt-2">
                        {{ __('pricing.credit_short') }}
                    </div>
                </div>

                <ul class="space-y-3 text-sm text-stone-600 mb-8">
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.diagnostic_benefit_audit') }}</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.diagnostic_benefit_roadmap') }}</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.diagnostic_benefit_notes') }}</span>
                    </li>
                </ul>
            </div>

            <div>
                <a data-cta="booking" data-primary-cta="diagnostic"
                   href="{{ $bookingUrl }}"
                   class="block w-full text-center bg-stone-900 hover:bg-black text-white font-bold text-sm py-3.5 px-6 rounded-2xl shadow-sm hover:shadow transition-all transform hover:-translate-y-0.5">
                    {{ __('pricing.cta_diagnostic') }}
                </a>
            </div>
        </div>

        <!-- 2. Foundation Coaching Track (Popular) -->
        <div class="bg-white rounded-3xl border-2 border-terracotta-500 p-8 flex flex-col justify-between shadow-md relative ring-4 ring-terracotta-100">
            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-terracotta-500 text-white text-xs font-extrabold uppercase tracking-wider py-1 px-4 rounded-full shadow-xs">
                {{ __('pricing.core_track') }}
            </div>
            <div>
                <div class="flex items-center justify-between gap-2 mb-4 mt-1">
                    <span class="text-xs font-bold uppercase tracking-wider text-terracotta-700 bg-terracotta-50 px-3 py-1 rounded-full">
                        {{ __('pricing.foundation_specs') }}
                    </span>
                    <span class="text-xs text-stone-500 font-medium font-mono">
                        {{ __('pricing.foundation_validity') }}
                    </span>
                </div>
                <h2 class="text-2xl font-extrabold text-stone-900 mb-2">
                    {{ __('pricing.foundation_title') }}
                </h2>
                <p class="text-stone-600 text-sm leading-relaxed mb-6">
                    {{ __('pricing.foundation_desc') }}
                </p>

                <div class="mb-6 pt-6 border-t border-stone-100 space-y-3">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs text-stone-500 uppercase tracking-wider font-semibold">{{ __('pricing.standard_price_label') }}</span>
                        <span class="text-xl font-bold text-stone-900 font-mono line-through text-stone-400">{{ __('pricing.foundation_standard_price') }}</span>
                    </div>
                    <div class="bg-stone-50 rounded-2xl p-3.5 border border-stone-200/80">
                        <div class="flex items-baseline justify-between">
                            <div>
                        <div class="text-xs text-stone-600 font-bold">{{ __('pricing.invoice_amount_label') }}</div>
                        <div class="text-xs text-emerald-600 font-medium">{{ __('pricing.after_credit') }}</div>
                            </div>
                            <div class="text-right">
                                <span class="text-3xl font-extrabold text-terracotta-600 font-mono">{{ __('pricing.foundation_invoice_price') }}</span>
                                <div class="text-xs text-stone-500 font-medium">({{ __('pricing.due_on_invoice') }})</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-stone-500 pt-1">
                        <span>{{ __('pricing.total_expenditure_label') }}:</span>
                        <span class="font-bold text-stone-800 font-mono">{{ __('pricing.foundation_total') }} ({{ __('pricing.diagnostic_price') }} + {{ __('pricing.foundation_invoice_price') }})</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-stone-500">
                        <span>{{ __('pricing.customer_effective_rate_label') }}</span>
                        <span class="font-semibold text-stone-700 font-mono">{{ __('pricing.foundation_effective_rate') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-stone-500">
                        <span>{{ __('pricing.invoice_equivalent_rate_label') }}</span>
                        <span class="font-semibold text-stone-700 font-mono">{{ __('pricing.foundation_invoice_rate') }}</span>
                    </div>
                </div>

                <ul class="space-y-3 text-sm text-stone-600 mb-8">
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-terracotta-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.foundation_benefit_classes') }}</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-terracotta-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.foundation_benefit_review') }}</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-terracotta-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.foundation_benefit_materials') }}</span>
                    </li>
                </ul>
            </div>

            <div>
                <a data-cta="booking" data-primary-cta="diagnostic"
                   href="{{ $bookingUrl }}"
                   class="block w-full text-center bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-sm py-3.5 px-6 rounded-2xl shadow-sm hover:shadow transition-all transform hover:-translate-y-0.5">
                    {{ __('pricing.cta_diagnostic') }}
                </a>
            </div>
        </div>

        <!-- 3. Fluency Immersion Track -->
        <div class="bg-white rounded-3xl border-2 border-stone-200 p-8 flex flex-col justify-between shadow-sm relative">
            <div>
                <div class="flex items-center justify-between gap-2 mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-nile-700 bg-nile-50 px-3 py-1 rounded-full">
                        {{ __('pricing.fluency_specs') }}
                    </span>
                    <span class="text-xs text-stone-500 font-medium font-mono">
                        {{ __('pricing.fluency_validity') }}
                    </span>
                </div>
                <h2 class="text-2xl font-extrabold text-stone-900 mb-2">
                    {{ __('pricing.fluency_title') }}
                </h2>
                <p class="text-stone-600 text-sm leading-relaxed mb-6">
                    {{ __('pricing.fluency_desc') }}
                </p>

                <div class="mb-6 pt-6 border-t border-stone-100 space-y-3">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs text-stone-500 uppercase tracking-wider font-semibold">{{ __('pricing.standard_price_label') }}</span>
                        <span class="text-xl font-bold text-stone-900 font-mono line-through text-stone-400">{{ __('pricing.fluency_standard_price') }}</span>
                    </div>
                    <div class="bg-stone-50 rounded-2xl p-3.5 border border-stone-200/80">
                        <div class="flex items-baseline justify-between">
                            <div>
                        <div class="text-xs text-stone-600 font-bold">{{ __('pricing.invoice_amount_label') }}</div>
                        <div class="text-xs text-emerald-600 font-medium">{{ __('pricing.after_credit') }}</div>
                            </div>
                            <div class="text-right">
                                <span class="text-3xl font-extrabold text-nile-700 font-mono">{{ __('pricing.fluency_invoice_price') }}</span>
                                <div class="text-xs text-stone-500 font-medium">({{ __('pricing.due_on_invoice') }})</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-stone-500 pt-1">
                        <span>{{ __('pricing.total_expenditure_label') }}:</span>
                        <span class="font-bold text-stone-800 font-mono">{{ __('pricing.fluency_total') }} ({{ __('pricing.diagnostic_price') }} + {{ __('pricing.fluency_invoice_price') }})</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-stone-500">
                        <span>{{ __('pricing.customer_effective_rate_label') }}</span>
                        <span class="font-semibold text-stone-700 font-mono">{{ __('pricing.fluency_effective_rate') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-stone-500">
                        <span>{{ __('pricing.invoice_equivalent_rate_label') }}</span>
                        <span class="font-semibold text-stone-700 font-mono">{{ __('pricing.fluency_invoice_rate') }}</span>
                    </div>
                </div>

                <ul class="space-y-3 text-sm text-stone-600 mb-8">
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-nile-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.fluency_benefit_classes') }}</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-nile-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.fluency_benefit_priority') }}</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-nile-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('pricing.fluency_benefit_voice') }}</span>
                    </li>
                </ul>
            </div>

            <div>
                <a data-cta="booking" data-primary-cta="diagnostic"
                   href="{{ $bookingUrl }}"
                   class="block w-full text-center bg-stone-900 hover:bg-black text-white font-bold text-sm py-3.5 px-6 rounded-2xl shadow-sm hover:shadow transition-all transform hover:-translate-y-0.5">
                    {{ __('pricing.cta_diagnostic') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Secondary / Exceptions Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-16">
        <!-- Pay-As-You-Go Maintenance -->
        <div class="bg-stone-50 rounded-2xl p-6 border border-stone-200/80 space-y-2">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-stone-900 text-base">{{ __('pricing.payg_title') }}</h3>
                <span class="text-sm font-mono font-extrabold text-stone-700">{{ __('pricing.payg_price') }} ({{ __('pricing.payg_rate') }})</span>
            </div>
            <p class="text-xs text-stone-500 font-medium font-mono">{{ __('pricing.payg_specs') }} • {{ __('pricing.payg_desc') }}</p>
        </div>

        <!-- Advanced Conversational -->
        <div class="bg-stone-50 rounded-2xl p-6 border border-stone-200/80 space-y-2">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-stone-900 text-base">{{ __('pricing.advanced_title') }}</h3>
                <span class="text-sm font-mono font-extrabold text-stone-700">{{ __('pricing.advanced_price') }} ({{ __('pricing.advanced_rate') }})</span>
            </div>
            <p class="text-xs text-stone-500 font-medium font-mono">{{ __('pricing.advanced_specs') }} • {{ __('pricing.advanced_desc') }}</p>
        </div>
    </div>

    <!-- Operational & Invoicing Disclosures -->
    <div class="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-10 shadow-sm text-stone-600 text-sm leading-relaxed space-y-4">
        <h2 class="text-lg font-bold text-stone-900">{{ __('pricing.invariants_title') }}</h2>
        <p>
            {{ __('pricing.invoice_note') }}
        </p>
        <p class="text-xs text-stone-500">
            {{ __('pricing.operational_note') }}
        </p>
    </div>
</div>
@endsection
