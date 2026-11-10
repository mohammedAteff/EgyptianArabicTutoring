@props(['sessionToken'])
<dialog class="student-quick-notes" id="student-quick-notes" aria-labelledby="quick-notes-heading" data-quick-notes data-session-token="{{ $sessionToken }}" data-create-url="{{ route('student.quick-notes.store') }}" data-session-url="{{ route('student.quick-notes.session') }}">
    <div class="student-quick-inner">
        <div class="student-quick-heading"><h2 id="quick-notes-heading">{{ __('Quick Notes') }}</h2><button type="button" data-quick-close aria-label="{{ __('Close Quick Notes') }}"><x-student-portal-icon name="close" /></button></div>
        <label for="quick-notes-body">{{ __('Your note') }}</label>
        <textarea id="quick-notes-body" dir="auto" rows="9" aria-describedby="quick-notes-status" data-quick-body></textarea>
        <p role="status" aria-live="polite" id="quick-notes-status" data-quick-status>{{ __('Write a note. Changes save automatically.') }}</p>
        <div class="student-quick-recovery"><button type="button" data-quick-retry hidden>{{ __('Retry') }}</button><button type="button" data-quick-restore hidden>{{ __('Restore last saved text') }}</button><button type="button" data-quick-load hidden>{{ __('Load current version') }}</button></div>
        <section class="student-quick-confirm" data-quick-confirm hidden aria-labelledby="quick-confirm-message"><p id="quick-confirm-message"></p><button type="button" data-quick-confirm-yes>{{ __('Continue') }}</button><button type="button" data-quick-confirm-no>{{ __('Keep writing') }}</button></section>
        <div class="student-quick-actions">
            <div class="student-quick-note-actions">
                <button type="button" data-quick-copy><x-student-portal-icon name="copy" data-quick-copy-icon /><x-student-portal-icon name="check" data-quick-copy-check /><span data-quick-copy-label aria-live="polite" aria-atomic="true">{{ __('Copy note text') }}</span></button>
                <button type="button" data-quick-bookmark aria-pressed="false"><x-student-portal-icon name="bookmarks" /><span data-quick-bookmark-label>{{ __('Save Bookmark') }}</span></button>
            </div>
            <button type="button" data-quick-new><x-student-portal-icon name="plus" /><span>{{ __('New note') }}</span></button>
            <a href="{{ route('student.bins.index') }}" data-quick-all><span>{{ __('Open all notes') }}</span><x-student-portal-icon name="arrow-right" /></a>
        </div>
    </div>
</dialog>
