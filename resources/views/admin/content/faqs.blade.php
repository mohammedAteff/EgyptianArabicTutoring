<div x-data="{ addFaqModalOpen: false }" class="space-y-5"><div class="flex flex-wrap gap-3"><a href="{{ route('faq.preview') }}" target="_blank" rel="noopener" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold">Preview FAQs</a><button type="button" @click="addFaqModalOpen = true" class="rounded-xl bg-amber-600 px-4 py-3 text-sm font-bold text-white">Add FAQ</button></div>    <!-- FAQs Management -->
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
                    $valTopic = $draftRevision && array_key_exists('topic', $draftRevision->content) ? $draftRevision->content['topic'] : $faq->topic;
                    $valFeatured = $draftRevision?->content['featured'] ?? $faq->featured;
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

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="text-xs font-semibold">Topic<select name="topic" class="mt-1 block w-full rounded-xl border border-slate-300 p-2"><option value="">General questions</option>@foreach(config('public_content.faq_topics') as $topic)<option value="{{ $topic }}" @selected($valTopic === $topic)>{{ $topic }}</option>@endforeach</select></label>
                                <label class="flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="featured" value="0"><input type="checkbox" name="featured" value="1" @checked($valFeatured)>Show on the homepage (up to six active FAQs)</label>
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

                        <div class="pt-3 border-t border-slate-200/60">
                            <x-admin.translations-panel :entity="$faq" entity-type="faq" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-slate-400 text-xs">
                    No FAQs added yet.
                </div>
            @endforelse
        </div>
    </div>

</div>