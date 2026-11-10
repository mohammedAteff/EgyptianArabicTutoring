@props(['name'])
@php
    $path = match ($name) {
        'home' => 'm3 10 9-7 9 7v11h-6v-7H9v7H3z',
        'sessions' => 'M4 5h16v16H4z M8 3v4m8-4v4M4 11h16m-11 4h2m2 0h2m-6 3h2',
        'learning' => 'M12 6c-3-2-6-2-9-1v14c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1Zm0 0v14',
        'insights' => 'M4 3v18h17M8 17v-5m5 5V8m5 9V5',
        'practice' => 'M4 4h16v12h-9l-6 4v-4H4z M8 8h8m-8 4h5',
        'planning' => 'M4 6h16M8 3v6m8-6v6M4 6v15h16V6M8 13h8m-8 4h5',
        'notepad' => 'M6 4h12a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2ZM8 2v4m8-4v4M8 10h8m-8 4h8m-8 4h5',
        'pencil' => 'M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z M15 5l4 4',
        'copy' => 'M10.5 8h8a2.5 2.5 0 0 1 2.5 2.5v8a2.5 2.5 0 0 1-2.5 2.5h-8A2.5 2.5 0 0 1 8 18.5v-8A2.5 2.5 0 0 1 10.5 8Z M16 8V5.5A2.5 2.5 0 0 0 13.5 3h-8A2.5 2.5 0 0 0 3 5.5v8A2.5 2.5 0 0 0 5.5 16H8',
        'check' => 'm5 12 4 4L19 6',
        'plus' => 'M12 5v14M5 12h14',
        'arrow-right' => 'M4 12h16m-6-6 6 6-6 6',
        'notes' => 'M5 3h14v18H5z M8 8h8m-8 4h8m-8 4h5',
        'bookmarks' => 'M6 3h12v18l-6-4-6 4z',
        'records' => 'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 8h6m-6 4h6m-6 4h3',
        'profile' => 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 6 0 0 1 16 0v2',
        'security' => 'm12 3 8 3v6c0 4-4 7-8 9-4-2-8-5-8-9V6z m-4 9 3 3 5-6',
        'browsers' => 'M3 4h18v14H3z M3 8h18M8 21h8m-4-3v3',
        'notifications' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M9 21h6',
        'logout' => 'M10 4H4v16h6m-1-8h12m-4-4 4 4-4 4',
        'chevron' => 'm6 9 6 6 6-6',
        'close' => 'm6 6 12 12M6 18 18 6',
        default => 'M4 6h16M4 12h16M4 18h16',
    };
@endphp
<svg {{ $attributes->class(['student-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}" /></svg>
