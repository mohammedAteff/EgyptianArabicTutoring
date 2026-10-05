<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\StaffBin;
use App\Domains\Administration\Models\StaffNotePreference;
use App\Domains\Administration\Services\StaffSavedViewService;
use App\Domains\Audit\Services\AuditLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaffBinController extends Controller
{
    public function index(Request $request, StaffSavedViewService $views): View
    {
        Gate::authorize('viewAny', StaffBin::class);
        $filters = $views->noteFilters($request);
        $administratorId = $request->user('web')->id;
        $query = StaffBin::query()->with(['author', 'pinnedBy'])->withExists([
            'preferences as my_pinned' => fn ($preferences) => $preferences->where('administrator_id', $administratorId)->where('pinned', true),
            'preferences as my_favorite' => fn ($preferences) => $preferences->where('administrator_id', $administratorId)->where('favorite', true),
        ]);
        if (($filters['collection'] ?? '') === 'shared') {
            $query->where('pinned', true);
        }
        if (in_array($filters['collection'] ?? '', ['mine', 'favorites'], true)) {
            $flag = $filters['collection'] === 'mine' ? 'pinned' : 'favorite';
            $query->whereHas('preferences', fn ($preferences) => $preferences->where('administrator_id', $administratorId)->where($flag, true));
        }
        if (! empty($filters['q'])) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $query->where(fn ($bins) => $bins->where('title', 'like', $like)->orWhere('body', 'like', $like));
        }
        if (($filters['owner'] ?? '') === 'mine') {
            $query->where('author_id', $request->user('web')->id);
        }
        $sort = $filters['sort'] ?? 'newest';
        $query->orderByDesc('pinned')->orderByDesc('my_pinned')->orderBy($sort === 'title' ? 'title' : 'updated_at', $sort === 'newest' ? 'desc' : 'asc')->orderBy('id');

        return view('admin.staff-bins', ['bins' => $query->paginate(20)->withQueryString(), 'filters' => $filters, 'editing' => null, 'savedViews' => $views->forSection($request->user('web'), 'staff_notes')]);
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

        return view('admin.staff-bins', ['bins' => collect(), 'filters' => [], 'editing' => $bin, 'savedViews' => collect()]);
    }

    public function sharedPin(Request $request, StaffBin $bin, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('sharedPin', $bin);
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $bin, $data, $audit): void {
            $locked = StaffBin::query()->lockForUpdate()->findOrFail($bin->id);
            $pinned = (bool) $data['pinned'];
            $locked->update(['pinned' => $pinned, 'pinned_by' => $pinned ? $request->user('web')->id : null, 'pinned_at' => $pinned ? now('UTC') : null]);
            $audit->log('staff_note_shared_pin_updated', StaffBin::class, $bin->id, null, ['pinned' => $pinned]);
        });

        return back()->with('success', 'Shared pin updated.');
    }

    public function personalize(Request $request, StaffBin $bin): RedirectResponse
    {
        Gate::authorize('personalize', $bin);
        $data = $request->validate(['flag' => ['required', 'in:pinned,favorite'], 'enabled' => ['required', 'boolean']]);
        StaffNotePreference::upsert([
            'administrator_id' => $request->user('web')->id, 'staff_bin_id' => $bin->id,
            'pinned' => $data['flag'] === 'pinned' && (bool) $data['enabled'],
            'favorite' => $data['flag'] === 'favorite' && (bool) $data['enabled'],
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ], ['administrator_id', 'staff_bin_id'], [$data['flag'], 'updated_at']);

        return back()->with('success', 'Your note preference was saved.');
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
