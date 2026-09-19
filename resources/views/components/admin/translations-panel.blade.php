@props(['entity', 'entityType'])

@php
    $currentRevision = $entity->currentSourceRevision()?->revision_number ?? 1;
    $locales = [
        'fr' => ['name' => 'French', 'flag' => '🇫🇷'],
        'de' => ['name' => 'German', 'flag' => '🇩🇪'],
    ];
@endphp

<div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900">Translations & Localization</h2>
            <p class="text-xs text-slate-500 mt-0.5">Manage French and German versions.</p>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono font-medium bg-slate-100 text-slate-700">
            Source Rev #{{ $currentRevision }}
        </span>
    </div>

    <div class="space-y-4">
        @foreach($locales as $loc => $info)
            @php
                $live = $entity->liveTranslation($loc);
                $draft = $entity->draftTranslation($loc);
                $isDraftOutdated = $draft && ((int) $draft->source_revision_id < (int) $currentRevision);
            @endphp

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                        <span>{{ $info['flag'] }}</span>
                        <span>{{ $info['name'] }} ({{ strtoupper($loc) }})</span>
                    </span>

                    @if($live)
                        @if($live->status === 'published')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                Live (v{{ $live->source_revision_id }})
                            </span>
                        @elseif($live->status === 'stale')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                Stale (v{{ $live->source_revision_id }})
                            </span>
                        @endif
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-200 text-slate-600">
                            Unpublished
                        </span>
                    @endif
                </div>

                @if($draft)
                    <div class="p-2.5 rounded-xl bg-white border border-slate-200/60 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-700">Active Draft</span>
                            <span class="text-[10px] font-mono text-slate-500">Based on Rev #{{ $draft->source_revision_id }}</span>
                        </div>

                        @if($isDraftOutdated)
                            <p class="text-[11px] text-amber-700 font-medium flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                English evolved to Rev #{{ $currentRevision }}. Reconciliation required before publish.
                            </p>
                        @endif
                    </div>
                @endif

                <div class="flex items-center gap-2 pt-1">
                    <a href="{{ route('admin.translations.reconcile', ['entityType' => $entityType, 'id' => $entity->getKey(), 'locale' => $loc]) }}"
                       class="flex-1 py-1.5 px-3 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-xs font-semibold transition-colors text-center">
                        @if($isDraftOutdated)
                            Reconcile & Edit Draft
                        @elseif($draft)
                            Edit {{ strtoupper($loc) }} Draft
                        @else
                            Translate to {{ strtoupper($loc) }}
                        @endif
                    </a>

                    @if($draft && ! $isDraftOutdated)
                        <form action="{{ route('admin.translations.publish', ['entityType' => $entityType, 'id' => $entity->getKey(), 'locale' => $loc]) }}" method="POST" onsubmit="return confirm('Publish this {{ strtoupper($loc) }} translation live?');">
                            @csrf
                            <button type="submit" class="py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition-colors">
                                Publish Live
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
