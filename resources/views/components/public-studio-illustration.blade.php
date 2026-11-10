@props(['name'])
<svg {{ $attributes->class(['studio-illustration']) }} viewBox="0 0 144 112" width="144" height="112" fill="none" stroke="var(--studio-teal)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="M28 92V49a44 44 0 0 1 88 0v43Z" fill="var(--studio-gold)" fill-opacity=".08" stroke="none" />
    <ellipse cx="72" cy="98" rx="59" ry="5" fill="var(--studio-teal)" fill-opacity=".07" stroke="none" />
    @switch($name)
        @case('goals')
            <g transform="rotate(-8 55 61)">
                <rect x="24" y="25" width="59" height="70" rx="4" fill="var(--studio-teal)" />
                <path d="M31 25v70" stroke="var(--studio-gold)" />
                <rect x="39" y="39" width="32" height="20" rx="2" fill="var(--studio-surface)" stroke="none" />
                <path d="M46 46h18m-18 6h11M41 72h23m-23 7h17" stroke="var(--studio-surface)" />
                <path d="M66 26v15l4-3 4 3V26" fill="var(--studio-clay)" stroke="none" />
            </g>
            <circle cx="100" cy="72" r="23" fill="var(--studio-surface)" />
            <circle cx="100" cy="72" r="18" stroke="var(--studio-gold)" />
            <path d="m107 58-3 18-11 10 3-18Z" fill="var(--studio-teal)" stroke="none" />
            <path d="m107 58-7 14-4-4Z" fill="var(--studio-clay)" stroke="none" />
            <circle cx="100" cy="72" r="2" fill="var(--studio-surface)" stroke="none" />
            <path d="M100 49v5m0 36v5m-23-23h5m36 0h5" />
            @break
        @case('explanations')
            <path d="M17 41q25-10 54 1 29-11 55-1v52q-25-9-55 1-29-10-54-1Z" fill="var(--studio-teal)" />
            <path d="M23 35q23-7 48 4 26-11 49-4v51q-23-7-49 5-25-12-48-5Z" fill="var(--studio-surface)" />
            <path d="M71 39v52M33 50q13-3 27 2m-27 8q13-3 27 2m-27 8q9-2 18 0m31-18q13-5 27-3m-27 13q13-5 27-3m-27 13q8-3 16-3" stroke-opacity=".5" />
            <path d="M94 33v25l6-5 6 2V32" fill="var(--studio-clay)" stroke="none" />
            <g transform="rotate(33 112 77)">
                <path d="M108 52h8v39l-4 7-4-7Z" fill="var(--studio-gold)" />
                <path d="M108 88h8m-4-36v36" />
                <path d="m110 95 2 3 2-3" fill="var(--studio-teal)" />
            </g>
            @break
        @case('continuity')
            <path d="M19 40v-8h34l8 8h58v54H19Z" fill="var(--studio-teal)" />
            <g transform="rotate(7 73 58)">
                <rect x="45" y="22" width="48" height="65" rx="3" fill="var(--studio-surface)" />
                <path d="M55 36h28m-28 8h20m-20 9h28m-28 8h22" stroke-opacity=".5" />
                <path d="m77 20 5-6 5 6v17l-5-3-5 3Z" fill="var(--studio-clay)" stroke="none" />
            </g>
            <path d="m19 55 10 39h90l10-39Z" fill="var(--studio-teal-soft)" />
            <path d="M43 72h35m-35 7h23" stroke-opacity=".5" />
            <circle cx="109" cy="80" r="19" fill="var(--studio-clay)" stroke="none" />
            <path d="M98 81v-3a11 11 0 0 1 22 0v3m-22-1h4v9h-4Zm18 0h4v9h-4Z" stroke="var(--studio-surface)" />
            @break
        @case('conversation')
            <path d="M27 50h31v29a13 13 0 0 1-13 13h-5a13 13 0 0 1-13-13Z" fill="var(--studio-teal)" />
            <path d="M58 57h5a9 9 0 0 1 0 18h-5" stroke-width="2.5" />
            <path d="m32 62 9 5 11-5m-20 9 9 5 11-5" stroke="var(--studio-gold)" />
            <path d="M86 61h29v20a11 11 0 0 1-11 11h-7a11 11 0 0 1-11-11Z" fill="var(--studio-clay)" stroke="none" />
            <path d="M115 67h5a8 8 0 0 1 0 16h-5" stroke="var(--studio-clay)" stroke-width="2.5" />
            <path d="M19 94h49m11 0h45M38 43c-6-6 5-8 0-15m58 26c-6-5 5-7 0-13" stroke-opacity=".55" />
            <path d="M60 17h42a5 5 0 0 1 5 5v13a5 5 0 0 1-5 5H80l-9 8v-8H60a5 5 0 0 1-5-5V22a5 5 0 0 1 5-5Z" fill="var(--studio-surface)" />
            <path d="M66 26h30m-30 6h20" />
            @break
        @case('assessment')
            <g transform="rotate(-7 57 58)">
                <rect x="26" y="22" width="62" height="73" rx="4" fill="var(--studio-teal)" />
                <rect x="33" y="28" width="55" height="65" rx="2" fill="var(--studio-surface)" />
                <path d="M43 39h32M43 51h4m10 0h18M43 63h4m10 0h18M43 75h4m10 0h14" stroke-opacity=".5" />
                <path d="M52 21h18v10H52Z" fill="var(--studio-gold)" stroke="none" />
            </g>
            <path d="m109 79 17 17" stroke-width="8" />
            <path d="m110 80 13 13" stroke="var(--studio-gold)" stroke-width="2" />
            <circle cx="97" cy="66" r="22" fill="var(--studio-surface)" stroke-width="3" />
            <circle cx="97" cy="66" r="17" fill="var(--studio-teal-soft)" stroke="none" />
            <path d="m87 66 7 7 13-15" stroke="var(--studio-clay)" stroke-width="3" />
            @break
        @case('roadmap')
            <path d="m18 31 35-9 36 10 36-9v68l-36 10-36-10-35 9Z" fill="var(--studio-surface)" />
            <path d="m53 22 36 10v69L53 91Z" fill="var(--studio-teal-soft)" stroke="none" />
            <path d="M53 22v69m36-59v69" stroke-opacity=".3" />
            <path d="M32 78c0-30 39-3 39-27s37 5 37-12" stroke="var(--studio-clay)" stroke-width="2.5" stroke-dasharray="3 5" />
            <circle cx="32" cy="78" r="4" fill="var(--studio-teal)" />
            <path d="M108 22v21m0-21h15l-4 5 4 5h-15" fill="var(--studio-gold)" />
            <path d="m102 83 5-12 5 12-5-3Z" fill="var(--studio-teal)" stroke="none" />
            <path d="M29 43h13m-13 6h9" stroke-opacity=".3" />
            @break
    @endswitch
</svg>
