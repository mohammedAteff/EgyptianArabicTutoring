@props(['name'])
<svg {{ $attributes->class(['studio-icon']) }} viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('activity')
            <circle cx="23" cy="12" r="3.5" fill="var(--studio-teal-soft)" />
            <path d="M21 19a8 8 0 0 1 9 8h-9" fill="var(--studio-teal-soft)" />
            <circle cx="12" cy="10" r="4.5" fill="currentColor" stroke="none" />
            <path d="M3 28v-3a9 9 0 0 1 18 0v3Z" fill="currentColor" stroke="none" />
            <path d="M8 23h8" stroke="var(--studio-surface)" />
            <circle cx="26" cy="5" r="3" fill="var(--studio-gold)" stroke="var(--studio-surface)" stroke-width="1.5" />
            @break
        @case('traffic')
            <rect x="2" y="5" width="28" height="23" rx="3" fill="var(--studio-surface)" />
            <path d="M5 5h22a3 3 0 0 1 3 3v4H2V8a3 3 0 0 1 3-3Z" fill="currentColor" stroke="none" />
            <circle cx="6" cy="8.5" r="1" fill="var(--studio-surface)" stroke="none" />
            <circle cx="10" cy="8.5" r="1" fill="var(--studio-gold)" stroke="none" />
            <path d="M7 21h3v4H7Zm7-4h3v8h-3Zm7-2h3v10h-3Z" fill="currentColor" stroke="none" />
            <path d="m22 3 4-2 4 2" stroke="var(--studio-gold)" />
            @break
        @case('practice-time')
            <path d="M2 7c4-2 8-1 12 1 4-2 8-3 12-1v18c-4-2-8-1-12 1-4-2-8-3-12-1Z" fill="currentColor" stroke="none" />
            <path d="M14 9v14M6 12l4 1m-4 5 4 1" stroke="var(--studio-surface)" />
            <path d="M20 6v6l-2-1-2 2V7Z" fill="var(--studio-gold)" stroke="none" />
            <circle cx="24" cy="23" r="7" fill="var(--studio-surface)" />
            <path d="M24 19v4l3 2" stroke-width="1.8" />
            <circle cx="24" cy="23" r="1" fill="var(--studio-gold)" stroke="none" />
            @break
        @case('arrow')
            <path d="M6 16h20m-7-7 7 7-7 7" />
            @break
        @case('goals')
            <circle cx="16" cy="16" r="12" />
            <path d="m22 10-4 10-8 2 3-9Z" fill="currentColor" stroke="none" />
            <circle cx="16" cy="16" r="1.5" fill="var(--studio-surface)" stroke="none" />
            <path d="M16 3v4" stroke="var(--studio-gold)" stroke-width="2.4" />
            @break
        @case('book')
            <path d="M3 6c4-2 9-1 12 1v21c-3-2-8-3-12-1Zm14 1c3-2 8-3 12-1v21c-4-2-9-1-12 1Z" fill="currentColor" stroke="none" />
            <path d="m7 12 4 1m-4 5 4 1m10-6 4-1m-4 7 4-1" stroke="var(--studio-surface)" />
            <path d="M24 5v7l-2-1-2 2V6Z" fill="var(--studio-gold)" stroke="none" />
            @break
        @case('progress')
            <path d="M4 20h6v8H4Zm9-6h6v14h-6Zm9-6h6v20h-6Z" fill="currentColor" stroke="none" />
            <path d="m5 12 8-7 6 2 8-5m-5 0h5v5" stroke="var(--studio-gold)" stroke-width="1.8" />
            @break
        @case('conversation')
            <path d="M23 12h6v14h-5v4l-5-4h-6v-4" stroke="var(--studio-gold)" />
            <path d="M3 4h21v15H12l-6 5v-5H3Z" fill="currentColor" stroke="none" />
            <path d="M8 9h11M8 14h7" stroke="var(--studio-surface)" />
            @break
        @case('assessment')
            <circle cx="13" cy="13" r="9" />
            <path d="m20 20 9 9" stroke-width="2.8" />
            <path d="m9 13 3 3 5-6" stroke="var(--studio-gold)" stroke-width="2" />
            @break
        @case('roadmap')
            <path d="M5 3h14l8 8v18H5Z" fill="currentColor" stroke="none" />
            <path d="M19 3v8h8Z" fill="var(--studio-gold)" stroke="none" />
            <path d="M11 13h5v10h5" stroke="var(--studio-surface)" />
            <circle cx="11" cy="13" r="1.7" fill="var(--studio-surface)" stroke="none" /><circle cx="21" cy="23" r="1.7" fill="var(--studio-surface)" stroke="none" />
            @break
        @case('games')
            <path d="M10 8h12c4 0 5 3 6 7l2 10c.6 4-3 5-5 2l-4-5H11l-4 5c-2 3-5.6 2-5-2l2-10c1-4 2-7 6-7Z" fill="currentColor" stroke="none" />
            <path d="M10 12v8m-4-4h8" stroke="var(--studio-surface)" stroke-width="2" />
            <circle cx="23" cy="14" r="1.8" fill="var(--studio-gold)" stroke="none" /><circle cx="26" cy="18" r="1.8" fill="var(--studio-surface)" stroke="none" />
            @break
        @case('tag')
            <path d="M4 4h10l14 14-10 10L4 14Z" fill="currentColor" stroke="none" />
            <circle cx="10" cy="10" r="2" fill="var(--studio-surface)" stroke="none" />
            @break
        @case('clock')
            <circle cx="16" cy="16" r="12" />
            <path d="M16 8v9l5 3" />
            @break
        @case('stopwatch')
            <path d="M16 5v4m8 1 2-2" stroke-width="2" />
            <path d="M12.5 1.5h7v4h-7Z" fill="var(--studio-gold)" stroke="none" />
            <path d="m24 6 4 4" stroke="var(--studio-gold)" stroke-width="2.5" />
            <circle cx="16" cy="19" r="10.5" fill="var(--studio-surface)" stroke-width="1.8" />
            <circle cx="16" cy="19" r="8" stroke="var(--studio-gold)" stroke-opacity=".45" stroke-width=".7" />
            <path d="M16 11v2m8 6h-2m-6 8v-2m-8-6h2" stroke-width="1" />
            <path d="M16 14v5l4 3" stroke-width="1.8" />
            <circle cx="16" cy="19" r="1.6" fill="var(--studio-clay)" stroke="none" />
            @break
        @case('person')
            <circle cx="16" cy="9" r="5" fill="currentColor" stroke="none" />
            <path d="M6 28v-3a10 10 0 0 1 20 0v3Z" fill="currentColor" stroke="none" />
            @break
    @endswitch
</svg>
