<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Models\Media;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(): View
    {
        $resources = Resource::query()
            ->with('category')
            ->withCount(['requests', 'downloads'])
            ->orderBy('sort_order')
            ->paginate(15);

        return view('admin.resources.index', [
            'title' => 'Learning Resources & Materials',
            'resources' => $resources,
        ]);
    }

    public function create(): View
    {
        $categories = ResourceCategory::query()->where('active', true)->orderBy('sort_order')->get();

        return view('admin.resources.create', [
            'title' => 'Add Learning Resource',
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:resources,slug'],
            'category_id' => ['required', 'exists:resource_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'file_type' => ['required', 'string', 'in:pdf,audio,zip,doc'],
            'is_gated' => ['nullable', 'in:0,1'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', 'in:draft,published,archived'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'file' => ['nullable', 'file', 'mimes:pdf,zip,doc,docx,mp3,wav,m4a', 'max:51200'], // 50MB max
            'cover_image_path' => ['nullable', 'string', 'max:255'],
            'cover_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $filePath = null;
        $fileSize = 0;

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $ext = $uploaded->getClientOriginalExtension() ?: $validated['file_type'];
            $fileName = $slug.'-'.Str::random(10).'.'.$ext;
            $filePath = $uploaded->storeAs('resources', $fileName, 'local');
            $fileSize = $uploaded->getSize();
        }

        $coverImagePath = $validated['cover_image_path'] ?? null;
        if ($request->hasFile('cover_file')) {
            $coverUploaded = $request->file('cover_file');
            $coverExt = $coverUploaded->getClientOriginalExtension();
            $coverName = Str::random(32).'.'.$coverExt;
            $coverPath = $coverUploaded->storeAs('media', $coverName, 'public');
            Media::create([
                'filename' => $coverUploaded->getClientOriginalName(),
                'disk' => 'public',
                'path' => $coverPath,
                'mime_type' => $coverUploaded->getMimeType(),
                'file_size' => $coverUploaded->getSize(),
                'alt_text' => $validated['title'].' Cover',
            ]);
            $coverImagePath = $coverPath;
        }

        $description = $validated['description'] ?? $validated['short_description'] ?? null;

        $resource = Resource::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'category_id' => $validated['category_id'],
            'short_description' => $description,
            'file_type' => $validated['file_type'],
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'cover_image_path' => $coverImagePath,
            'is_gated' => $request->boolean('is_gated', true),
            'featured' => $request->boolean('featured'),
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'resource_created',
            'entity_type' => Resource::class,
            'entity_id' => $resource->id,
            'new_data' => $resource->toArray(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.resources.index')->with('success', 'Resource created successfully.');
    }

    public function edit(Resource $resource): View
    {
        $categories = ResourceCategory::query()->where('active', true)->orderBy('sort_order')->get();
        $draftRevision = $resource->revisions()->where('status', 'draft')->latest('id')->first();

        return view('admin.resources.edit', [
            'title' => 'Edit Resource — '.$resource->title,
            'resource' => $resource,
            'draftRevision' => $draftRevision,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:resources,slug,'.$resource->id],
            'category_id' => ['required', 'exists:resource_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'file_type' => ['required', 'string', 'in:pdf,audio,zip,doc'],
            'is_gated' => ['nullable', 'in:0,1'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', 'in:draft,published,archived'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'file' => ['nullable', 'file', 'mimes:pdf,zip,doc,docx,mp3,wav,m4a', 'max:51200'],
            'cover_image_path' => ['nullable', 'string', 'max:255'],
            'cover_file' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $action = $request->input('action');
        $isDraftAction = $action === 'draft' || ($resource->status === 'published' && $validated['status'] === 'draft');

        $description = $validated['description'] ?? $validated['short_description'] ?? null;
        $slug = Str::slug($validated['slug']);

        $updates = [
            'title' => $validated['title'],
            'slug' => $slug,
            'category_id' => $validated['category_id'],
            'short_description' => $description,
            'file_type' => $validated['file_type'],
            'is_gated' => $request->boolean('is_gated', true),
            'featured' => $request->boolean('featured'),
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ];

        if (array_key_exists('cover_image_path', $validated)) {
            $updates['cover_image_path'] = $validated['cover_image_path'];
        }

        $oldFilePath = $resource->file_path;
        $newFilePath = null;
        $oldCoverPath = $resource->cover_image_path;
        $newCoverPath = null;

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $ext = $uploaded->getClientOriginalExtension() ?: $validated['file_type'];
            $fileName = $slug.'-'.Str::random(10).'.'.$ext;
            try {
                $newFilePath = $uploaded->storeAs('resources', $fileName, 'local');
            } catch (\Throwable $e) {
                return back()->withInput()->with('error', 'File upload failed: '.$e->getMessage());
            }

            if (! $newFilePath || ! Storage::disk('local')->exists($newFilePath)) {
                if ($newFilePath && Storage::disk('local')->exists($newFilePath)) {
                    Storage::disk('local')->delete($newFilePath);
                }

                return back()->withInput()->with('error', 'Failed to store resource file.');
            }

            $updates['file_path'] = $newFilePath;
            $updates['file_size'] = $uploaded->getSize();
        }

        if ($request->hasFile('cover_file')) {
            $coverUploaded = $request->file('cover_file');
            $coverExt = $coverUploaded->getClientOriginalExtension();
            $coverName = Str::random(32).'.'.$coverExt;
            try {
                $newCoverPath = $coverUploaded->storeAs('media', $coverName, 'public');
            } catch (\Throwable $e) {
                if ($newFilePath && Storage::disk('local')->exists($newFilePath)) {
                    Storage::disk('local')->delete($newFilePath);
                }

                return back()->withInput()->with('error', 'Cover image upload failed: '.$e->getMessage());
            }

            if (! $newCoverPath || ! Storage::disk('public')->exists($newCoverPath)) {
                if ($newFilePath && Storage::disk('local')->exists($newFilePath)) {
                    Storage::disk('local')->delete($newFilePath);
                }

                return back()->withInput()->with('error', 'Failed to store cover image.');
            }

            $updates['cover_image_path'] = $newCoverPath;
        }

        $existingDraft = $resource->revisions()->where('status', 'draft')->latest('id')->first();

        if ($isDraftAction && $resource->status === 'published') {
            $draftFilePath = $newFilePath ?? $existingDraft?->content['file_path'] ?? $resource->file_path;
            $draftFileSize = $request->hasFile('file') ? $request->file('file')->getSize() : ($existingDraft?->content['file_size'] ?? $resource->file_size);
            $draftCoverPath = $newCoverPath ?? $existingDraft?->content['cover_image_path'] ?? $resource->cover_image_path;

            $nextRevision = ($resource->revisions()->max('revision_number') ?? 0) + 1;
            ContentRevision::create([
                'revisable_type' => Resource::class,
                'revisable_id' => $resource->id,
                'revision_number' => $nextRevision,
                'title' => $validated['title'],
                'content' => [
                    'slug' => $slug,
                    'category_id' => $validated['category_id'],
                    'short_description' => $description,
                    'file_type' => $validated['file_type'],
                    'file_path' => $draftFilePath,
                    'file_size' => $draftFileSize,
                    'cover_image_path' => $draftCoverPath,
                    'is_gated' => $request->boolean('is_gated', true),
                    'featured' => $request->boolean('featured'),
                    'sort_order' => $validated['sort_order'] ?? 0,
                ],
                'created_by_id' => Auth::id(),
                'status' => 'draft',
            ]);

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'resource_draft_saved',
                'entity_type' => Resource::class,
                'entity_id' => $resource->id,
                'new_data' => [
                    'revision_number' => $nextRevision,
                    'title' => $validated['title'],
                ],
                'created_at' => now(),
            ]);

            return redirect()->route('admin.resources.edit', $resource)
                ->with('success', "Draft revision #{$nextRevision} saved. The live published resource remains unchanged.");
        }

        if ($action === 'publish' || $validated['status'] === 'published') {
            $updates['status'] = 'published';
            if (! $resource->published_at) {
                $updates['published_at'] = now();
            }
            if (! $newFilePath && $existingDraft && ! empty($existingDraft->content['file_path']) && $existingDraft->content['file_path'] !== $resource->file_path) {
                $updates['file_path'] = $existingDraft->content['file_path'];
                $updates['file_size'] = $existingDraft->content['file_size'] ?? $resource->file_size;
                $newFilePath = $existingDraft->content['file_path'];
            }
            if (! $newCoverPath && $existingDraft && ! empty($existingDraft->content['cover_image_path']) && $existingDraft->content['cover_image_path'] !== $resource->cover_image_path) {
                $updates['cover_image_path'] = $existingDraft->content['cover_image_path'];
                $newCoverPath = $existingDraft->content['cover_image_path'];
            }
        }

        try {
            DB::transaction(function () use ($resource, $updates, $request, $newCoverPath, $validated) {
                if ($request->hasFile('cover_file') && $newCoverPath) {
                    $coverUploaded = $request->file('cover_file');
                    Media::create([
                        'filename' => $coverUploaded->getClientOriginalName(),
                        'disk' => 'public',
                        'path' => $newCoverPath,
                        'mime_type' => $coverUploaded->getMimeType(),
                        'file_size' => $coverUploaded->getSize(),
                        'alt_text' => $validated['title'].' Cover',
                    ]);
                }

                $resource->revisions()->where('status', 'draft')->update(['status' => 'archived']);

                $prev = $resource->toArray();
                $resource->update($updates);

                $nextRevision = ($resource->revisions()->max('revision_number') ?? 0) + 1;
                ContentRevision::create([
                    'revisable_type' => Resource::class,
                    'revisable_id' => $resource->id,
                    'revision_number' => $nextRevision,
                    'title' => $resource->title,
                    'content' => [
                        'slug' => $resource->slug,
                        'category_id' => $resource->category_id,
                        'short_description' => $resource->short_description,
                        'file_type' => $resource->file_type,
                        'file_path' => $resource->file_path,
                        'file_size' => $resource->file_size,
                        'cover_image_path' => $resource->cover_image_path,
                        'is_gated' => $resource->is_gated,
                        'featured' => $resource->featured,
                        'sort_order' => $resource->sort_order,
                    ],
                    'created_by_id' => Auth::id(),
                    'status' => $resource->status === 'published' ? 'published' : 'draft',
                ]);

                AuditLog::create([
                    'administrator_id' => Auth::id(),
                    'action' => 'resource_updated',
                    'entity_type' => Resource::class,
                    'entity_id' => $resource->id,
                    'previous_data' => $prev,
                    'new_data' => $resource->toArray(),
                    'created_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            if ($newFilePath && Storage::disk('local')->exists($newFilePath)) {
                Storage::disk('local')->delete($newFilePath);
            }
            if ($newCoverPath && Storage::disk('public')->exists($newCoverPath)) {
                Storage::disk('public')->delete($newCoverPath);
            }

            return back()->withInput()->with('error', 'Failed to update resource: '.$e->getMessage());
        }

        // Retire old files only after commit and only if unreferenced elsewhere
        if ($newFilePath && $oldFilePath && $oldFilePath !== $newFilePath) {
            $isReferencedElsewhere = Media::isPathReferenced($oldFilePath, $resource->id);
            if (! $isReferencedElsewhere && Storage::disk('local')->exists($oldFilePath)) {
                Storage::disk('local')->delete($oldFilePath);
            }
        }

        if ($newCoverPath && $oldCoverPath && $oldCoverPath !== $newCoverPath) {
            $isCoverReferencedElsewhere = Media::isPathReferenced($oldCoverPath, $resource->id);
            if (! $isCoverReferencedElsewhere && Storage::disk('public')->exists($oldCoverPath)) {
                Storage::disk('public')->delete($oldCoverPath);
            }
        }

        return redirect()->route('admin.resources.index')->with('success', 'Resource updated successfully.');
    }

    public function destroy(Resource $resource): RedirectResponse
    {
        $prev = $resource->toArray();

        // Delete physical file
        if ($resource->file_path && Storage::disk('local')->exists($resource->file_path)) {
            Storage::disk('local')->delete($resource->file_path);
        }

        $resource->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'resource_deleted',
            'entity_type' => Resource::class,
            'entity_id' => $resource->id,
            'previous_data' => $prev,
            'created_at' => now(),
        ]);

        return redirect()->route('admin.resources.index')->with('success', 'Resource deleted successfully.');
    }

    public function discardDraft(Resource $resource): RedirectResponse
    {
        $drafts = $resource->revisions()->where('status', 'draft')->get();
        foreach ($drafts as $draft) {
            $draftFilePath = $draft->content['file_path'] ?? null;
            $draftCoverPath = $draft->content['cover_image_path'] ?? null;

            $draft->delete();

            if ($draftFilePath && $draftFilePath !== $resource->file_path && ! Media::isPathReferenced($draftFilePath)) {
                Storage::disk('local')->delete($draftFilePath);
            }
            if ($draftCoverPath && $draftCoverPath !== $resource->cover_image_path && ! Media::isPathReferenced($draftCoverPath)) {
                Storage::disk('public')->delete($draftCoverPath);
            }
        }

        return redirect()->route('admin.resources.edit', $resource)
            ->with('success', 'Draft revision discarded. Reverted to live published values.');
    }
}
