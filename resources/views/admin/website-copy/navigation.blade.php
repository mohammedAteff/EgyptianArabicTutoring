<div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-3" aria-label="Website content workspace">
@foreach(['copy' => 'Website Copy', 'faqs' => 'FAQs', 'pages' => 'Legal & Custom Pages'] as $key => $label)
<a href="{{ route('admin.website-copy.index', ['tab' => $key]) }}" @if(($tab ?? 'pages') === $key) aria-current="page" @endif class="rounded-xl px-4 py-3 text-sm font-semibold {{ ($tab ?? 'pages') === $key ? 'bg-amber-600 text-white' : 'text-slate-700 hover:bg-slate-100' }}">{{ $label }}</a>
@endforeach
</div>
