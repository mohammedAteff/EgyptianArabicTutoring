<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\StaffSavedView;
use App\Domains\Administration\Services\StaffSavedViewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffSavedViewController extends Controller
{
    public function store(Request $request, StaffSavedViewService $views): RedirectResponse
    {
        $section = $views->section($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'filters' => ['nullable', 'array']]);
        $filters = $views->validateFilters($section, $data['filters'] ?? []);
        $owned = StaffSavedView::query()->where('administrator_id', $request->user('web')->id)->where('section', $section);
        if ((clone $owned)->count() >= 20 && ! (clone $owned)->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['name' => 'Remove an existing saved view before adding another.']);
        }
        StaffSavedView::updateOrCreate(['administrator_id' => $request->user('web')->id, 'section' => $section, 'name' => $data['name']], ['filters' => $filters]);

        return redirect()->route(StaffSavedViewService::ROUTES[$section], $filters)->with('success', 'Your view was saved.');
    }

    public function apply(Request $request, int $savedView, StaffSavedViewService $views): RedirectResponse
    {
        $view = StaffSavedView::query()->where('administrator_id', $request->user('web')->id)->findOrFail($savedView);
        $filters = $views->validateFilters($view->section, $view->filters);

        return redirect()->route(StaffSavedViewService::ROUTES[$view->section], $filters);
    }

    public function destroy(Request $request, int $savedView): RedirectResponse
    {
        $view = StaffSavedView::query()->where('administrator_id', $request->user('web')->id)->findOrFail($savedView);
        $section = $view->section;
        $view->delete();

        return redirect()->route(StaffSavedViewService::ROUTES[$section])->with('success', 'Saved view removed.');
    }
}
