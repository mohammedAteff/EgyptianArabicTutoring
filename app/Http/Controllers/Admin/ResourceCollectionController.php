<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCollection;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\ResourceCollectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResourceCollectionController extends Controller
{
    public function index(): View
    {
        return view('admin.resources.collections', ['collections' => ResourceCollection::query()->with('resources:id,title')->orderBy('title')->get(), 'resources' => Resource::query()->published()->orderBy('title')->get(['id', 'title']), 'students' => Student::tutoringRoster()->orderBy('name_normalized')->get(['id', 'first_name', 'last_name'])]);
    }

    public function save(Request $request, ResourceCollectionService $service, ?ResourceCollection $collection = null): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'slug' => ['nullable', 'alpha_dash', 'max:160', Rule::unique('resource_collections')->ignore($collection?->id)], 'description' => ['nullable', 'string', 'max:5000'], 'is_published' => ['required', 'boolean'], 'resource_ids' => ['required', 'array', 'min:1', 'max:100'], 'resource_ids.*' => ['required', 'integer', 'distinct']]);
        $data['slug'] = $data['slug'] ?? $collection?->slug ?? Str::substr(Str::slug($data['title']), 0, 145).'-'.Str::lower(Str::random(8));
        $service->save($collection, $data, $request->user('web')->id);

        return to_route('admin.resource-collections.index')->with('success', 'Resource collection saved.');
    }

    public function assign(Request $request, ResourceCollection $collection, ResourceCollectionService $service): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        $student = Student::query()->findOrFail($data['student_id']);
        Gate::authorize('manageTeaching', $student);
        $service->assign($student, $collection, $request->user('web')->id);

        return back()->with('success', 'Collection assigned to the student.');
    }
}
