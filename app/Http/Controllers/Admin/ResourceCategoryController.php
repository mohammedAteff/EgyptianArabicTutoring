<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Services\TranslationService;
use App\Domains\Resources\Models\ResourceCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResourceCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ResourceCategory::query()
            ->withCount('resources')
            ->orderBy('sort_order')
            ->get();

        return view('admin.resource-categories.index', [
            'title' => 'Resource Categories',
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        return view('admin.resource-categories.create', [
            'title' => 'New Resource Category',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:resource_categories,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['active'] = $request->boolean('active', true);

        $category = ResourceCategory::create($validated);

        app(TranslationService::class)->updateEnglishSource($category, [
            'name' => $category->name,
            'description' => null,
        ], Auth::id());

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'resource_category_created',
            'entity_type' => ResourceCategory::class,
            'entity_id' => $category->id,
            'new_data' => $category->toArray(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.resource-categories.index')->with('success', "Category '{$category->name}' created.");
    }

    public function edit(ResourceCategory $resourceCategory): View
    {
        return view('admin.resource-categories.edit', [
            'title' => 'Edit Category — '.$resourceCategory->name,
            'category' => $resourceCategory,
        ]);
    }

    public function update(Request $request, ResourceCategory $resourceCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('resource_categories', 'slug')->ignore($resourceCategory->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['active'] = $request->boolean('active', false);

        $prev = $resourceCategory->toArray();

        DB::transaction(function () use ($resourceCategory, $validated, $prev) {
            $lockedCategory = ResourceCategory::where('id', $resourceCategory->id)->lockForUpdate()->firstOrFail();
            $lockedCategory->update($validated);

            app(TranslationService::class)->updateEnglishSource($lockedCategory, [
                'name' => $lockedCategory->name,
                'description' => null,
            ], Auth::id());

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'resource_category_updated',
                'entity_type' => ResourceCategory::class,
                'entity_id' => $lockedCategory->id,
                'previous_data' => $prev,
                'new_data' => $lockedCategory->toArray(),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.resource-categories.index')->with('success', "Category '{$resourceCategory->name}' updated.");
    }

    public function destroy(ResourceCategory $resourceCategory): RedirectResponse
    {
        $resourceCount = $resourceCategory->resources()->withTrashed()->count();

        if ($resourceCount > 0) {
            return back()->with('error', "Cannot delete category '{$resourceCategory->name}': it is associated with {$resourceCount} active or archived resource(s). Please reassign or delete them first.");
        }

        $prev = $resourceCategory->toArray();
        $categoryName = $resourceCategory->name;
        $resourceCategory->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'resource_category_deleted',
            'entity_type' => ResourceCategory::class,
            'entity_id' => $resourceCategory->id,
            'previous_data' => $prev,
            'created_at' => now(),
        ]);

        return redirect()->route('admin.resource-categories.index')->with('success', "Category '{$categoryName}' deleted.");
    }
}
