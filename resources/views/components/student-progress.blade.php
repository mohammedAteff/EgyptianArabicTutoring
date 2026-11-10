@props(['progress', 'readOnly' => false, 'variant' => null])
@php
    $metrics = [];
    $inconsistent = false;
    if ($progress['completed_lessons'] > 0) {
        $metrics['Completed lessons'] = (string) $progress['completed_lessons'];
    }
    foreach (['milestones' => ['Milestones completed', 'milestones_completed'], 'homework' => ['Homework completed', 'homework_completed'], 'errors' => ['Patterns improved or resolved', 'errors_improved']] as $category => [$label, $numerator]) {
        $total = $progress[$category.'_total'];
        $count = $progress[$numerator];
        if ($total > 0 || $count > 0) {
            $metrics[$label] = $count.' / '.$total;
        }
        $inconsistent = $inconsistent || ($count > 0 && $total === 0);
    }
@endphp
<section aria-label="{{ __('Learning progress') }}" @class(['student-surface mt-7', 'student-surface--white' => $variant === 'card'])>
    <h2 class="text-xl font-semibold text-nile-900">{{ __('Your Learning Progress') }}</h2>
    <p class="mt-1 text-sm text-stone-500">{{ __('Recorded lesson and practice milestones shared by your tutor.') }}</p>
    @if(count($metrics))
    <dl class="student-progress-metrics mt-4">
        @foreach($metrics as $label => $value)
        <div><dt class="text-sm text-stone-500">{{ __($label) }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $value }}</dd></div>
        @endforeach
    </dl>
    @if($inconsistent)<p class="mt-3 text-sm text-stone-600">{{ __('Some recorded progress counts have no corresponding total. These records need review.') }}</p>@endif
    @else
    <p class="student-progress-empty mt-4 text-sm text-stone-600">{{ __('Your progress will appear here as you complete lessons and learning activities.') }}</p>
    @endif
    @if(!$readOnly)
    <a href="{{ route('student.bins.index') }}" class="{{ $variant === 'card' ? 'student-button student-button-secondary mt-5' : 'mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline' }}">{{ __('View Learning Notes') }} @if($progress['shared_notes'] > 0)({{ $progress['shared_notes'] }})@endif</a>
    @endif
</section>
