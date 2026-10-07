@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wider text-amber-700">Author preview · Staff only</p><h1 class="mt-2 font-serif text-3xl font-bold text-slate-900" dir="auto">{{ $graph['title'] }}</h1></div><a href="{{ route('admin.lms.courses.edit',$course) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700">← Back to editor</a></div>
    <p class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">This shows authoring content, including draft and unpublished lessons. It grants no learner access and records no Student learning activity.</p>
    @forelse($graph['sections'] as $section)
    @if($section['status']!=='archived')
    <section class="space-y-5"><h2 class="text-xl font-bold text-slate-900" dir="auto">{{ $section['title'] }} <span class="text-xs font-semibold capitalize text-slate-500">({{ $section['status'] }})</span></h2>
        @foreach($section['lessons'] as $lesson)
        @if($lesson['status']!=='archived')
        <article class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-xs sm:p-8">
            <h3 class="text-lg font-bold text-slate-900" dir="auto">{{ $lesson['title'] }} <span class="text-xs font-semibold capitalize text-slate-500">({{ $lesson['status'] }})</span></h3>
            @foreach($lesson['blocks'] as $block)
            @if($block['status']!=='withdrawn')
            @if(isset($block['preview_error'])||$block['status']!=='ready')
                <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ $block['preview_error']??'This content is unavailable and cannot be active in a published lesson.' }}</p>
            @elseif($block['kind']==='rich_text')
                <div dir="auto" class="break-words text-sm leading-8 text-slate-700 [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_h3]:text-lg [&_h3]:font-bold [&_ul]:list-disc [&_ul]:ps-6 [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:my-3 [&_a]:text-amber-700 [&_a]:underline [&_blockquote]:border-s-4 [&_blockquote]:border-amber-300 [&_blockquote]:ps-4">{!! $block['payload']['html'] !!}</div>
            @elseif($block['kind']==='image')
                <figure class="space-y-2"><img loading="lazy" src="{{ route('admin.lms.courses.assets',[$course,$block['asset_id']]) }}" alt="{{ $block['payload']['alt'] }}" class="max-h-[32rem] w-full rounded-2xl object-contain"><figcaption class="text-xs text-slate-500" dir="auto">{{ $block['payload']['alt'] }}</figcaption></figure>
            @elseif(in_array($block['kind'],['file','resource'],true))
                <a href="{{ !empty($block['asset_id'])?route('admin.lms.courses.assets',[$course,$block['asset_id']]):route('admin.lms.courses.resources',[$course,$block['resource_id']]) }}" target="_blank" rel="noopener" class="flex min-h-14 items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-amber-300"><span dir="auto">{{ $block['kind']==='file'?($block['payload']['label']??'Download file'):($resources->get($block['resource_id'])?->title??'Open Resource') }}</span><span aria-hidden="true">↗</span></a>
            @elseif($block['kind']==='external_link')
                <a href="{{ $block['payload']['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 break-all items-center rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">{{ $block['payload']['url'] }} ↗</a>
            @elseif(in_array($block['kind'],['youtube_video','external_video'],true))
                @if(($block['payload']['provider']??'')==='direct')
                    <video controls preload="metadata" src="{{ $block['payload']['url'] }}" class="aspect-video w-full rounded-2xl bg-slate-900">Your browser cannot play this video. <a href="{{ $block['payload']['url'] }}">Open the video</a>.</video>
                @else
                    <iframe loading="lazy" src="{{ $block['payload']['embed_url'] }}" title="{{ $lesson['title'] }} video" sandbox="allow-scripts allow-same-origin allow-presentation" allow="fullscreen; picture-in-picture" allowfullscreen referrerpolicy="no-referrer" class="aspect-video w-full rounded-2xl border-0 bg-slate-900"></iframe>
                @endif
                <p class="text-xs text-slate-500">External video preview. Availability is controlled by its provider.</p>
            @endif
            @endif
            @endforeach
        </article>
        @endif
        @endforeach
    </section>
    @endif
    @empty
    <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">Add sections, lessons and content in the editor to preview this course.</p>
    @endforelse
</div>
@endsection
