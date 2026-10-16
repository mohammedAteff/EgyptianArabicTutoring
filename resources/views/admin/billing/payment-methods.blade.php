@extends('layouts.admin')
@section('content')
<x-lifecycle-feedback />
<div class="space-y-6">
    <div><h1 class="text-3xl font-bold text-slate-900">Payment Methods</h1><p class="mt-2 text-slate-500">Enable methods for new manual payments. Each payment keeps its original label and stable method reference.</p></div>
    <form method="POST" action="{{ route('admin.payment-methods.store') }}" class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-6">
        @csrf
        <label class="flex-1">Method name<input name="name" required maxlength="80" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></label>
        <label>Order<input name="sort_order" type="number" min="0" max="10000" value="0" required class="mt-1 w-24 rounded-xl border border-slate-300 p-3"></label>
        <button class="self-end rounded-xl bg-amber-600 px-5 py-3 font-bold text-white">Add method</button>
    </form>
    @foreach($methods as $method)
        <form method="POST" action="{{ route('admin.payment-methods.update', $method) }}" class="flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PUT')
            <span class="py-3 text-sm text-slate-500">#{{ $loop->iteration }}</span>
            <label class="flex-1">Name<input name="name" value="{{ $method->name }}" required maxlength="80" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></label>
            <label>Order<input name="sort_order" type="number" min="0" max="10000" required value="{{ $method->sort_order }}" class="mt-1 w-24 rounded-xl border border-slate-300 p-3"></label>
            <input type="hidden" name="active" value="0"><label class="pb-3"><input type="checkbox" name="active" value="1" @checked($method->active)> Enabled</label>
            <input type="hidden" name="is_default" value="0"><label class="pb-3"><input type="checkbox" name="is_default" value="1" @checked($method->is_default)> Default</label>
            <button class="rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white">Save method</button>
            <button form="remove-method-{{ $method->id }}" class="rounded-xl border border-red-200 px-4 py-3 font-semibold text-red-700">Remove method</button>
        </form>
        <form id="remove-method-{{ $method->id }}" method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" onsubmit="return confirm('Remove this payment method? Previously recorded payments keep their original method and reference.');">@csrf @method('DELETE')</form>
    @endforeach
    @if($removedMethods->isNotEmpty())<details class="rounded-2xl border border-slate-200 bg-white p-5 text-sm"><summary class="cursor-pointer font-semibold">Removed payment methods</summary>@foreach($removedMethods as $method)<form method="POST" action="{{ route('admin.payment-methods.restore', $method->id) }}" class="mt-3 flex items-center justify-between gap-3">@csrf<span>{{ $method->name }}</span><button class="rounded-lg border border-slate-300 px-3 py-2 font-semibold">Restore method</button></form>@endforeach</details>@endif
</div>
@endsection
