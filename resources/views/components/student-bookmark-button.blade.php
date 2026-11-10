@props(['kind', 'targetId', 'bookmark' => null, 'iconOnly' => false, 'compact' => false])
<form method="POST" action="{{ $bookmark ? route('student.bookmarks.destroy', $bookmark) : route('student.bookmarks.store') }}" @class(['mt-2' => ! $iconOnly && ! $compact])>
    @csrf
    @if($bookmark) @method('DELETE')
    @else<input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="target_id" value="{{ $targetId }}">@endif
    <button type="submit" @if($iconOnly) aria-label="{{ __($bookmark ? 'Remove Bookmark' : 'Save Bookmark') }}" title="{{ __($bookmark ? 'Remove Bookmark' : 'Save Bookmark') }}" aria-pressed="{{ $bookmark ? 'true' : 'false' }}" @endif class="{{ $iconOnly ? 'student-note-bookmark-icon' : 'min-h-11 text-sm font-semibold text-nile-800 underline' }}">
        @if($iconOnly)<x-student-portal-icon name="bookmarks" />
        @else{{ __($bookmark ? 'Remove Bookmark' : 'Save Bookmark') }}@endif
    </button>
</form>
