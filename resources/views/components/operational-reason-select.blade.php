@props(['category' => null, 'name' => 'reason_code', 'label' => 'Reason (optional)'])
@php
    $reasons = $category
        ? \App\Domains\Administration\Services\OperationalReasonCatalog::forCategory($category)
        : \App\Domains\Administration\Services\OperationalReasonCatalog::all();
@endphp
<label class="block text-sm font-semibold text-slate-700">{{ $label }}
    <select name="{{ $name }}" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-3">
        <option value="">Choose a reason</option>
        @foreach($reasons as $code => $reasonLabel)
            <option value="{{ $code }}" @selected(old($name) === $code)>{{ $reasonLabel }}</option>
        @endforeach
    </select>
</label>
