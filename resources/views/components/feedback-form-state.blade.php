@csrf
<input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}">
@if($actor instanceof \App\Domains\Students\Models\Student)<input type="hidden" name="_feedback_session" value="{{ Illuminate\Support\Facades\Crypt::encryptString(json_encode(['student_id' => $actor->id, 'session_id' => session()->getId()])) }}">@endif
<div data-feedback-copy hidden data-saving="{{ __('feedback.saving') }}" data-saved="{{ __('feedback.saved') }}" data-failed="{{ __('feedback.failed') }}" data-session="{{ __('feedback.session_expired') }}" data-uncertain="{{ __('feedback.uncertain') }}" data-context-empty="{{ __('feedback.no_context_matches') }}" data-context-failed="{{ __('feedback.context_failed') }}" data-acknowledged="{{ __('feedback.acknowledged') }}"></div>
<div data-feedback-status role="status" tabindex="-1" class="break-words text-sm leading-7 [overflow-wrap:anywhere]"></div>
<div data-feedback-recovery hidden class="space-y-3 text-sm">
    <p>{{ __('feedback.keyboard_hint') }}</p>
    <div class="flex flex-wrap gap-3">
        <a href="{{ route($baseRoute === 'student.feedback' ? 'student.login' : 'admin.login') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center underline">{{ __('feedback.sign_in') }}</a>
        <a href="{{ $historyUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center underline">{{ __('feedback.check_history') }}</a>
    </div>
    <button type="button" data-feedback-new-operation hidden class="min-h-11 rounded-xl border border-slate-300 px-3 text-start font-semibold">{{ __('feedback.new_operation') }}</button>
</div>
@if($showDraftNotice ?? true)<p class="text-xs leading-6 text-slate-500">{{ __('feedback.draft_notice') }}</p>@endif
<noscript><p class="text-sm">{{ __('feedback.no_script') }}</p></noscript>
