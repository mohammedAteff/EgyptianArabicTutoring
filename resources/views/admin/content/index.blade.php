@extends('layouts.admin')

@section('content')
<div class="space-y-8" x-data="{ addFaqModalOpen: false }">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Content, FAQs & Social Channels</h1>
            <p class="text-sm text-slate-500 mt-1">Manage public frequently asked questions and official social media/WhatsApp contact channels.</p>
        <div class="flex items-center gap-2">
            <a href="{{ route('faq.preview') }}" target="_blank" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors shadow-xs">
                Preview FAQs &rarr;
            </a>
            <button @click="addFaqModalOpen = true" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                + Add New FAQ
            </button>
        </div>
    </div>

    <!-- FAQs Management -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        <div>
            <h2 class="text-lg font-bold font-serif text-slate-900">Frequently Asked Questions</h2>
            <p class="text-xs text-slate-500">Displayed on the homepage and dedicated FAQ page to answer common prospective student questions.</p>
        </div>

        <div class="space-y-4">
            @forelse($faqs as $faq)
                @php
                    $draftRevision = $faq->revisions->where('status', 'draft')->first();
                    $hasDraft = (bool) $draftRevision;
                    $valQuestion = $draftRevision?->title ?? $draftRevision?->content['question'] ?? $faq->question;
                    $valAnswer = $draftRevision?->content['answer'] ?? $faq->answer;
                    $valSortOrder = $draftRevision?->content['sort_order'] ?? $faq->sort_order;
                    $valActive = $draftRevision ? ($draftRevision->content['active'] ?? $faq->active) : $faq->active;
                @endphp
                <div x-data="{ editing: {{ $hasDraft ? 'true' : 'false' }} }" class="bg-slate-50 rounded-2xl p-5 border {{ $hasDraft ? 'border-amber-300 ring-1 ring-amber-200' : 'border-slate-200/80' }} space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 bg-slate-200 text-slate-700 rounded-md text-[10px] font-bold">#{{ $faq->sort_order }}</span>
                                <span class="font-bold text-slate-900 text-sm">{{ $faq->question }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $faq->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $faq->active ? 'Active' : 'Hidden' }}
                                </span>
                                @if($hasDraft)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Draft Pending (Rev #{{ $draftRevision->revision_number }})
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">{{ $faq->answer }}</p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" @click="editing = !editing" class="px-3 py-1 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg shadow-xs transition-colors">
                                <span x-text="editing ? 'Close' : 'Edit'"></span>
                            </button>

                            <form action="{{ route('admin.content.faq.destroy', $faq->id) }}" method="POST" onsubmit="return confirm('Delete this FAQ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-red-600 rounded-lg hover:bg-slate-100 transition-colors" title="Delete FAQ">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Edit Form -->
                    <div x-show="editing" x-cloak class="pt-4 border-t border-slate-200/80 space-y-4">
                        @if($hasDraft)
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between gap-4">
                                <div class="text-xs text-amber-800">
                                    <span class="font-bold">Pending Draft Revision #{{ $draftRevision->revision_number }} loaded.</span> Changes are not visible to the public until published.
                                </div>
                                <form action="{{ route('admin.content.faq.draft.destroy', $faq->id) }}" method="POST" onsubmit="return confirm('Discard this draft and revert to live published values?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1 bg-white border border-amber-300 hover:bg-amber-100 text-amber-900 text-xs font-semibold rounded-lg shadow-xs">
                                        Discard Draft
                                    </button>
                                </form>
                            </div>
                        @endif

                        <form action="{{ route('admin.content.faq.update', $faq->id) }}" method="POST" class="space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Question</label>
                                <input type="text" name="question" value="{{ $valQuestion }}" required 
                                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Answer</label>
                                <textarea name="answer" rows="3" required 
                                          class="w-full p-3 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ $valAnswer }}</textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Sort Order</label>
                                    <input type="number" name="sort_order" value="{{ $valSortOrder }}" min="0" 
                                           class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Status</label>
                                    <select name="active" class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                        <option value="1" {{ $valActive ? 'selected' : '' }}>Active (Published)</option>
                                        <option value="0" {{ ! $valActive ? 'selected' : '' }}>Hidden</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2">
                                <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900">
                                    Cancel
                                </button>
                                <button type="submit" name="action" value="draft" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition-colors">
                                    Save as Draft
                                </button>
                                <button type="submit" name="action" value="publish" class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors font-serif">
                                    Publish FAQ
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-slate-400 text-xs">
                    No FAQs added yet.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Custom Pages Summary & Quick Links -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold font-serif text-slate-900">Custom Standalone Pages</h2>
                <p class="text-xs text-slate-500">Legal notices, curriculum overviews, and student resources with versioned content revisions.</p>
            </div>
            <a href="{{ route('admin.pages.index') }}" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors font-serif shadow-xs">
                Manage All Pages &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @forelse($pages->take(3) as $p)
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-xs text-slate-900">{{ $p->title }}</div>
                        <div class="text-[10px] text-slate-500 font-mono">/p/{{ $p->slug }}</div>
                    </div>
                    <a href="{{ route('admin.pages.edit', $p->id) }}" class="text-xs font-semibold text-amber-600 hover:text-amber-700">
                        Edit
                    </a>
                </div>
            @empty
                <div class="col-span-full py-4 text-slate-400 text-xs text-center">
                    No custom pages created yet. <a href="{{ route('admin.pages.create') }}" class="text-amber-600 underline">Create one now</a>.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Social Channels & WhatsApp Support (Spec 50 & 51) -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        <div>
            <h2 class="text-lg font-bold font-serif text-slate-900">Official Social Media & WhatsApp Channels</h2>
            <p class="text-xs text-slate-500">Displayed in footer and contact sections. WhatsApp includes pre-filled conversational intent messages.</p>
        </div>

        <form action="{{ route('admin.content.social.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="space-y-4">
                @foreach($socials as $soc)
                    <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3 sm:w-48 shrink-0">
                            <label class="flex items-center gap-2 cursor-pointer text-sm font-bold text-slate-900 capitalize">
                                <input type="checkbox" name="socials[{{ $soc->id }}][enabled]" value="1" {{ $soc->enabled ? 'checked' : '' }}
                                       class="w-4 h-4 rounded-sm border-slate-300 text-amber-600 focus:ring-amber-500">
                                <span>{{ $soc->platform }}</span>
                            </label>
                        </div>

                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">
                                    {{ $soc->platform === 'whatsapp' ? 'Phone Number (Intl)' : 'Profile URL' }}
                                </label>
                                <input type="text" name="socials[{{ $soc->id }}][url_or_phone]" value="{{ $soc->url_or_phone }}" required
                                       class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                            </div>

                            @if($soc->platform === 'whatsapp')
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Default Pre-filled Message</label>
                                    <input type="text" name="socials[{{ $soc->id }}][default_message]" value="{{ $soc->default_message }}"
                                           class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                            @else
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Display Label</label>
                                    <input type="text" name="socials[{{ $soc->id }}][label]" value="{{ $soc->label }}" required
                                           class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors font-serif shadow-xs">
                    Save Social Channels
                </button>
            </div>
        </form>
    </div>

    <!-- Modal: Add New FAQ -->
    <div x-show="addFaqModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" 
         style="display: none;">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl border border-slate-200" @click.away="addFaqModalOpen = false">
            <h3 class="text-lg font-bold font-serif text-slate-900 mb-1">Add Frequently Asked Question</h3>
            <p class="text-xs text-slate-500 mb-6">Create an authoritative answer for prospective students.</p>

            <form action="{{ route('admin.content.faq.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Question</label>
                    <input type="text" name="question" required placeholder="e.g. Do I need to know Arabic script before starting?" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Answer</label>
                    <textarea name="answer" rows="4" required placeholder="Clear, encouraging explanation..."
                              class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Status</label>
                        <select name="active" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <option value="1">Active (Published)</option>
                            <option value="0">Hidden</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="addFaqModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs">
                        Save FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
