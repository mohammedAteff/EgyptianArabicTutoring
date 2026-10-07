@props(['id','value'=>'draft'])
<div>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Publication state</label>
    <select id="{{ $id }}" name="status" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm focus:outline-2 focus:outline-amber-600">
        @foreach(['draft'=>'Draft','published'=>'Published','unpublished'=>'Unpublished','archived'=>'Archived'] as $status=>$label)
            <option value="{{ $status }}" @selected($value === $status)>{{ $label }}</option>
        @endforeach
    </select>
</div>
