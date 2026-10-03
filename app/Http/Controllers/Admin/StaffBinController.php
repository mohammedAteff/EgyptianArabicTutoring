<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\StaffBin;
use App\Domains\Audit\Services\AuditLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaffBinController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StaffBin::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'sort' => ['nullable', 'in:newest,oldest,title'], 'owner' => ['nullable', 'in:mine,all']]);
        $query = StaffBin::query()->with('author');
        if (! empty($filters['q'])) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $query->where(fn ($bins) => $bins->where('title', 'like', $like)->orWhere('body', 'like', $like));
        }
        if (($filters['owner'] ?? '') === 'mine') {
            $query->where('author_id', $request->user('web')->id);
        }
        $sort = $filters['sort'] ?? 'newest';
        $query->orderBy($sort === 'title' ? 'title' : 'updated_at', $sort === 'newest' ? 'desc' : 'asc')->orderBy('id');

        return view('admin.staff-bins', ['bins' => $query->paginate(20)->withQueryString(), 'filters' => $filters, 'editing' => null]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('create', StaffBin::class);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:30000']]);
        $bin = StaffBin::create([...$data, 'author_id' => $request->user('web')->id]);
        $audit->log('staff_bin_created', StaffBin::class, $bin->id, null, ['owner_id' => $bin->author_id]);

        return redirect()->route('admin.staff-bins.index')->with('success', 'Staff note created.');
    }

    public function edit(StaffBin $bin): View
    {
        Gate::authorize('update', $bin);

        return view('admin.staff-bins', ['bins' => collect(), 'filters' => [], 'editing' => $bin]);
    }

    public function update(Request $request, StaffBin $bin, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('update', $bin);
        $bin->update($request->validate(['title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:30000']]));
        $audit->log('staff_bin_updated', StaffBin::class, $bin->id, null, ['owner_id' => $bin->author_id]);

        return redirect()->route('admin.staff-bins.index')->with('success', 'Staff note updated.');
    }

    public function destroy(Request $request, StaffBin $bin, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('delete', $bin);
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);
        DB::transaction(function () use ($bin, $audit): void {
            $audit->log('staff_bin_deleted', StaffBin::class, $bin->id, ['owner_id' => $bin->author_id], ['deleted_at' => now('UTC')->toIso8601String()]);
            $bin->delete();
        });

        return redirect()->route('admin.staff-bins.index')->with('success', 'Staff note removed. Deletion history retained.');
    }
}
