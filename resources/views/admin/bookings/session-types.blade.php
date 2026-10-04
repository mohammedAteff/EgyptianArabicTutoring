@extends('layouts.admin')
@section('content')
<h1 class="text-2xl font-bold">Lesson types &amp; funding</h1>
<p class="mt-2 text-slate-600">Choose the purchased entitlement separately from lesson duration. Existing bookings retain their original funding.</p>
<div class="mt-6 space-y-6">
@foreach($sessionTypes->push(new \App\Domains\Booking\Models\SessionType) as $type)
<form method="POST" action="{{ route('admin.session-types.save', $type->exists ? $type->id : null) }}" class="grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2 lg:grid-cols-3">
@csrf
<h2 class="font-bold sm:col-span-2 lg:col-span-3">{{ $type->title ?? 'New lesson type' }}</h2>
@foreach(['title'=>'Title','slug'=>'Stable slug','duration_minutes'=>'Duration (minutes)','price'=>'Price','currency'=>'Currency'] as $field=>$label)<label>{{ $label }}<input name="{{ $field }}" required value="{{ old($field, $type->$field ?? ($field === 'currency' ? 'USD' : '')) }}" class="mt-1 block w-full rounded-lg border border-slate-300 p-3"></label>@endforeach
<label>Funding mode<select name="funding_mode" class="mt-1 block w-full rounded-lg border border-slate-300 p-3">@foreach(['package'=>'Package entitlement','direct'=>'Direct payment','free'=>'Free','legacy'=>'Legacy / review required'] as $mode=>$label)<option value="{{ $mode }}" @selected(($type->funding_mode ?? 'legacy') === $mode)>{{ $label }}</option>@endforeach</select></label>
<label>Required entitlement<select name="required_entitlement_type_id" class="mt-1 block w-full rounded-lg border border-slate-300 p-3"><option value="">None</option>@foreach($entitlementTypes as $entitlement)<option value="{{ $entitlement->id }}" @selected($type->required_entitlement_type_id === $entitlement->id)>{{ $entitlement->label }}</option>@endforeach</select></label>
<label>Required units<input type="number" name="required_entitlement_units" min="1" max="100" value="{{ $type->required_entitlement_units }}" class="mt-1 block w-full rounded-lg border border-slate-300 p-3"></label>
<label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked($type->active)>Active</label>
<button class="min-h-11 rounded-lg bg-slate-900 px-4 py-3 text-white">Save lesson type</button>
</form>@endforeach</div>
@endsection
