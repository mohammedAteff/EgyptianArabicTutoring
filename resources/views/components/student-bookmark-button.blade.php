@props(['kind', 'targetId'])
<form method="POST" action="{{ route('student.bookmarks.store') }}" class="mt-2">@csrf<input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="target_id" value="{{ $targetId }}"><button class="min-h-11 text-sm font-semibold text-nile-800 underline">Save Bookmark</button></form>
