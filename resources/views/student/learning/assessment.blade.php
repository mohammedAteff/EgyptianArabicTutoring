@extends('layouts.student', ['title' => $block->payload['title']])
@section('content')
<x-estimated-activity-bootstrap :contexts="$activityContexts ?? []" />
<div id="activity-assessment" class="mx-auto max-w-3xl space-y-6">
    <a href="{{ route('student.learning.lessons.show', [$course, $lesson]) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">{{ __('← Return to lesson') }}</a>
    @include('student.learning.feedback')
    <h1 dir="auto" class="break-words text-3xl font-bold">{{ $block->payload['title'] }}</h1>
    @if($block->kind === 'quiz')
        <p class="text-sm text-stone-600">{{ trans_choice('Pass at :score% · Up to :count attempt for this version.|Pass at :score% · Up to :count attempts for this version.', $block->payload['attempt_limit'], ['score' => localized_number($block->payload['passing_score']), 'count' => localized_number($block->payload['attempt_limit'])]) }}</p>
        @if($canStart)<form method="POST" action="{{ route('student.evidence.quiz.begin', [$course,$lesson,$block]) }}">@csrf<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button class="min-h-11 rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">{{ __('Start / continue quiz') }}</button></form>@else<p class="text-sm text-stone-600">{{ __('You have used all attempts for this quiz version.') }}</p>@endif
        @forelse($attempts as $attempt)
            <article class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="font-semibold" dir="auto">{{ $attempt['title'] }} {{ __('· Attempt') }} {{ $attempt['number'] }}</h2>
                <p class="text-sm">{{ __(str_replace('_',' ',ucfirst($attempt['status']))) }}@if($attempt['score'] !== null) · {{ $attempt['score'] }}% · {{ __($attempt['passed'] ? 'Passed' : 'Try again') }}@endif</p>
                @if($attempt['feedback'])<p dir="auto" class="whitespace-pre-wrap text-sm text-stone-600">{{ $attempt['feedback'] }}</p>@endif
                @if($attempt['status'] === 'started')
                    <form method="POST" action="{{ route('student.evidence.quiz.submit', [$course,$lesson,$block,$attempt['id']]) }}" class="space-y-5">@csrf
                        @foreach($attempt['questions'] as $i => $question)
                            <fieldset class="space-y-3 rounded-xl border border-stone-200 p-4">
                                <legend dir="auto" class="px-1 font-semibold">{{ $i+1 }}. {{ $question['prompt'] }} <span class="text-xs font-normal text-stone-500">({{ trans_choice(':count point|:count points', $question['points'], ['count' => localized_number($question['points'])]) }})</span></legend>
                                @if(in_array($question['type'],['choice','multiple'],true))
                                    @foreach($question['options'] as $option => $label)<label class="flex min-h-11 items-center gap-3 text-sm"><input type="{{ $question['type']==='choice' ? 'radio' : 'checkbox' }}" name="answers[{{ $i }}]{{ $question['type']==='multiple' ? '[]' : '' }}" value="{{ $option }}"><span dir="auto" class="break-words">{{ $label }}</span></label>@endforeach
                                @elseif($question['type'] === 'boolean')
                                    @foreach(['true'=>'True','false'=>'False'] as $value=>$label)<label class="flex min-h-11 items-center gap-3 text-sm"><input type="radio" name="answers[{{ $i }}]" value="{{ $value }}" required>{{ __($label) }}</label>@endforeach
                                @elseif($question['type'] === 'matching')
                                    @foreach($question['options'] as $option=>$label)<label class="grid gap-2 text-sm sm:grid-cols-2"><span dir="auto">{{ $label }}</span><select name="answers[{{ $i }}][{{ $option }}]" class="min-h-11 rounded-lg border border-stone-300 px-3" required><option value="">{{ __('Choose a match') }}</option>@foreach($question['targets'] as $target)<option value="{{ $target }}">{{ $target }}</option>@endforeach</select></label>@endforeach
                                @else
                                    <label class="block text-sm">{{ __('Your answer') }}<textarea dir="auto" name="answers[{{ $i }}]" required maxlength="{{ $question['type']==='written' ? 10000 : 500 }}" rows="{{ $question['type']==='written' ? 4 : 2 }}" class="mt-2 w-full rounded-lg border border-stone-300 p-3"></textarea></label>
                                @endif
                            </fieldset>
                        @endforeach
                        <button class="min-h-11 rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">{{ __('Submit answers') }}</button>
                    </form>
                @else
                    @foreach($attempt['questions'] as $i=>$question)
                        <div class="space-y-2 border-t border-stone-100 pt-3 text-sm"><p dir="auto" class="font-medium">{{ $question['prompt'] }}</p><p dir="auto" class="whitespace-pre-wrap break-words text-stone-600">{{ __('Your answer:') }} {{ $question['response_label'] }}</p>
                        @if(array_key_exists('correct',$question))<p class="text-nile-800" dir="auto">{{ __('Review:') }} {{ $question['review_label'] }} · {{ $question['mark'] }}/{{ $question['points'] }}</p>@endif</div>
                    @endforeach
                @endif
            </article>
        @empty<p class="text-sm text-stone-600">{{ __('Start the quiz when you are ready. Your attempt is saved for later.') }}</p>@endforelse
    @else
        <p dir="auto" class="whitespace-pre-wrap break-words text-sm leading-7 text-stone-600">{{ $block->payload['instructions'] }}</p>
        <p class="text-sm font-semibold">{{ __($currentSubmission ? str_replace('_',' ',ucwords($currentSubmission->status,'_')) : 'Not Submitted') }}</p>
        @if($canSubmit)
        <form data-lms-assignment x-data="{kind:@js($block->payload['types'][0])}" method="POST" enctype="multipart/form-data" action="{{ route('student.evidence.assignment.submit', [$course,$lesson,$block]) }}" class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5">
            @csrf<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <label class="block text-sm font-medium">{{ __('Response type') }}<select data-submission-kind x-model="kind" name="kind" class="mt-2 min-h-11 w-full rounded-lg border border-stone-300 px-3">@foreach($block->payload['types'] as $type)<option value="{{ $type }}">{{ __(ucfirst(str_replace('_',' ',$type))) }}</option>@endforeach</select></label>
            <label x-show="kind==='text'" class="block text-sm">{{ __('Written response') }}<textarea :disabled="kind!=='text'" dir="auto" name="body" maxlength="20000" rows="5" class="mt-2 w-full rounded-lg border border-stone-300 p-3"></textarea></label>
            <label x-show="kind==='external_link'" class="block text-sm">{{ __('External link') }}<input :disabled="kind!=='external_link'" type="url" name="url" maxlength="2000" placeholder="{{ __('https://…') }}" class="mt-2 min-h-11 w-full rounded-lg border border-stone-300 px-3"></label>
            <label x-show="['file','audio','video'].includes(kind)" class="block text-sm">{{ __('Private attachment') }}<input :disabled="!['file','audio','video'].includes(kind)" data-submission-file type="file" name="file" class="mt-2 block max-w-full text-sm"><span class="mt-2 block text-xs text-stone-500">{{ __('Files: PDF, JPG, PNG, TXT up to 10 MB. Audio: MP3, WAV, OGG, M4A, WebM up to 25 MB. Video: MP4, WebM up to 50 MB.') }}</span></label>
            @if(in_array('audio',$block->payload['types'],true))<div class="flex flex-wrap items-center gap-3"><button data-audio-record type="button" class="min-h-11 rounded-xl border border-stone-300 px-4 text-sm">{{ __('Record audio') }}</button><p data-audio-status role="status" class="text-xs text-stone-500">{{ __('Recording is optional; you can upload an audio file.') }}</p></div>@endif
            <button class="min-h-11 rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">{{ __('Submit / resubmit') }}</button>
        </form>
        @else<p class="text-sm text-stone-600">{{ __('Your response is saved. A new response becomes available if the reviewer requests a revision.') }}</p>@endif
        <section class="space-y-4"><h2 class="text-xl font-semibold">{{ __('Submission history') }}</h2>
            @foreach($submissions as $submission)<article class="space-y-2 rounded-2xl border border-stone-200 bg-white p-5"><h3 class="font-semibold">{{ __('Revision') }} {{ $submission->number }} · {{ __(str_replace('_',' ',ucwords($submission->status,'_'))) }}</h3><p dir="auto" class="whitespace-pre-wrap break-words text-sm">{{ $submission->body }}</p>@if($submission->url)<a href="{{ $submission->url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center break-all text-sm text-nile-800">{{ __('Open submitted link ↗') }}</a>@endif @if($submission->path)<a href="{{ route('student.evidence.file',$submission) }}" class="inline-flex min-h-11 items-center text-sm text-nile-800">{{ __('Download your private attachment') }}</a>@endif @if($submission->feedback)<p dir="auto" class="whitespace-pre-wrap text-sm text-stone-600">{{ $submission->feedback }}</p>@endif</article>@endforeach
        </section>
    @endif
</div>
@endsection
