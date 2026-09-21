<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\TranslationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        $pages = Page::query()
            ->withCount('revisions')
            ->orderBy('title')
            ->paginate(20);

        return view('admin.pages.index', [
            'title' => 'Page CMS & Revisions',
            'pages' => $pages,
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.create', [
            'title' => 'Create New Page',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:pages,slug'],
            'content' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'og_image_path' => ['nullable', 'string', 'max:255'],
        ]);

        $slug = Str::slug($validated['slug']);

        $page = Page::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'] ?? '',
            'excerpt' => $validated['excerpt'] ?? null,
            'status' => $validated['status'],
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'og_image_path' => $validated['og_image_path'] ?? null,
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        app(TranslationService::class)->updateEnglishSource($page, [
            'title' => $page->title,
            'content' => $page->content ?? '',
            'excerpt' => $page->excerpt,
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
        ], Auth::id());

        // Create initial revision #1
        ContentRevision::create([
            'revisable_type' => Page::class,
            'revisable_id' => $page->id,
            'revision_number' => 1,
            'title' => $page->title,
            'content' => [
                'body' => $page->content,
                'excerpt' => $page->excerpt,
                'og_image_path' => $page->og_image_path,
            ],
            'created_by_id' => Auth::id(),
            'status' => $page->status,
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'page_created',
            'entity_type' => Page::class,
            'entity_id' => $page->id,
            'new_data' => $page->toArray(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.pages.index')
            ->with('success', "Page '{$page->title}' created successfully.");
    }

    public function edit(Page $page): View
    {
        $revisions = $page->revisions()->orderByDesc('revision_number')->get();
        $draftRevision = $page->revisions()
            ->where('status', 'draft')
            ->latest('id')
            ->first();

        return view('admin.pages.edit', [
            'title' => "Edit Page — {$page->title}",
            'page' => $page,
            'revisions' => $revisions,
            'draftRevision' => $draftRevision,
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('pages')->ignore($page->id)],
            'content' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'og_image_path' => ['nullable', 'string', 'max:255'],
        ]);

        $action = $request->input('action');
        $isDraftAction = $action === 'draft' || ($page->status === 'published' && $validated['status'] === 'draft');

        $slug = Str::slug($validated['slug']);
        $pendingDraft = $page->revisions()
            ->where('status', 'draft')
            ->latest('id')
            ->first();

        // A publish action from the edit screen should publish the pending
        // draft, even if an older browser tab submitted the still-live values.
        // Explicitly changed fields continue to win over the stored draft.
        if ($action === 'publish' && $pendingDraft) {
            $draftContent = $pendingDraft->content ?? [];
            $draftValues = [
                'title' => $pendingDraft->title,
                'content' => $draftContent['body'] ?? null,
                'excerpt' => $draftContent['excerpt'] ?? null,
                'seo_title' => $draftContent['seo_title'] ?? null,
                'seo_description' => $draftContent['seo_description'] ?? null,
                'og_image_path' => $draftContent['og_image_path'] ?? null,
            ];
            foreach (['title', 'content', 'excerpt', 'seo_title', 'seo_description', 'og_image_path'] as $field) {
                $liveValue = $page->{$field};
                $draftValue = $draftValues[$field];
                if (($validated[$field] ?? null) === $liveValue && $draftValue !== $liveValue) {
                    $validated[$field] = $draftValue;
                }
            }

            if ($validated['slug'] === $page->slug && isset($draftContent['slug'])) {
                $validated['slug'] = $draftContent['slug'];
                $slug = Str::slug($validated['slug']);
            }
        }

        $previousData = $page->toArray();
        $nextRevision = ($page->revisions()->max('revision_number') ?? 0) + 1;

        if ($isDraftAction) {
            $draftRevision = $page->revisions()
                ->where('status', 'draft')
                ->latest('id')
                ->first();

            $draftData = [
                'revisable_type' => Page::class,
                'revisable_id' => $page->id,
                'revision_number' => $draftRevision?->revision_number ?? $nextRevision,
                'title' => $validated['title'],
                'content' => [
                    'slug' => $slug,
                    'body' => $validated['content'] ?? '',
                    'excerpt' => $validated['excerpt'] ?? null,
                    'og_image_path' => $validated['og_image_path'] ?? null,
                    'seo_title' => $validated['seo_title'] ?? null,
                    'seo_description' => $validated['seo_description'] ?? null,
                ],
                'created_by_id' => Auth::id(),
                'status' => 'draft',
            ];

            if ($draftRevision) {
                $draftRevision->update($draftData);
            } else {
                ContentRevision::create($draftData);
            }

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'page_draft_saved',
                'entity_type' => Page::class,
                'entity_id' => $page->id,
                'new_data' => [
                    'revision_number' => $draftData['revision_number'],
                    'title' => $validated['title'],
                ],
                'created_at' => now(),
            ]);

            return redirect()->route('admin.pages.edit', $page->id)
                ->with('success', "Draft saved as Revision #{$draftData['revision_number']}. The published version remains live until explicitly published.");
        }

        $targetStatus = ($action === 'publish' || $validated['status'] === 'published') ? 'published' : $validated['status'];
        $publishedAt = $page->published_at;
        if ($targetStatus === 'published' && ! $publishedAt) {
            $publishedAt = now();
        }

        DB::transaction(function () use ($page, $validated, $slug, $targetStatus, $publishedAt, $previousData, $nextRevision) {
            $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();
            $draftRevision = ContentRevision::query()
                ->where('revisable_type', Page::class)
                ->where('revisable_id', $lockedPage->id)
                ->where('status', 'draft')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $lockedPage->update([
                'title' => $validated['title'],
                'slug' => $slug,
                'content' => $validated['content'] ?? '',
                'excerpt' => $validated['excerpt'] ?? null,
                'status' => $targetStatus,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'og_image_path' => $validated['og_image_path'] ?? null,
                'published_at' => $publishedAt,
            ]);

            app(TranslationService::class)->updateEnglishSource($lockedPage, [
                'title' => $validated['title'],
                'content' => $validated['content'] ?? '',
                'excerpt' => $validated['excerpt'] ?? null,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
            ], Auth::id());

            $revisionData = [
                'title' => $validated['title'],
                'content' => [
                    'slug' => $slug,
                    'body' => $validated['content'] ?? '',
                    'excerpt' => $validated['excerpt'] ?? null,
                    'og_image_path' => $validated['og_image_path'] ?? null,
                    'seo_title' => $validated['seo_title'] ?? null,
                    'seo_description' => $validated['seo_description'] ?? null,
                ],
                'created_by_id' => Auth::id(),
                'status' => $targetStatus === 'published' ? 'published' : 'draft',
            ];

            if ($draftRevision) {
                $draftRevision->update($revisionData);
            } else {
                ContentRevision::create(array_merge($revisionData, [
                    'revisable_type' => Page::class,
                    'revisable_id' => $lockedPage->id,
                    'revision_number' => $nextRevision,
                ]));
            }

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => $targetStatus === 'published' ? 'page_published' : 'page_updated',
                'entity_type' => Page::class,
                'entity_id' => $lockedPage->id,
                'previous_data' => $previousData,
                'new_data' => $lockedPage->toArray(),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.pages.index')
            ->with('success', "Page '{$page->title}' updated (Revision #{$nextRevision} saved).");
    }

    public function discardDraft(Page $page): RedirectResponse
    {
        $discardedRevision = DB::transaction(function () use ($page): ?ContentRevision {
            $lockedPage = Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $draft = ContentRevision::query()
                ->where('revisable_type', Page::class)
                ->where('revisable_id', $lockedPage->id)
                ->where('status', 'draft')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $draft?->delete();

            return $draft;
        });

        if ($discardedRevision) {
            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'page_draft_discarded',
                'entity_type' => Page::class,
                'entity_id' => $page->id,
                'new_data' => ['revision_number' => $discardedRevision->revision_number],
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Page draft discarded. The published version remains unchanged.');
    }

    public function restoreRevision(Page $page, ContentRevision $revision): RedirectResponse
    {
        if ($revision->revisable_type !== Page::class || (int) $revision->revisable_id !== (int) $page->id) {
            return back()->with('error', 'Revision does not belong to this page.');
        }

        $content = $revision->content['body'] ?? '';
        $excerpt = $revision->content['excerpt'] ?? null;
        $ogImagePath = $revision->content['og_image_path'] ?? $page->og_image_path;
        $title = $revision->title ?? $page->title;

        DB::transaction(function () use ($page, $revision, $content, $excerpt, $ogImagePath, $title) {
            $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();

            $lockedPage->update([
                'title' => $title,
                'content' => $content,
                'excerpt' => $excerpt,
                'og_image_path' => $ogImagePath,
            ]);

            // Synchronize canonical English translation, record new source snapshot, and stale FR/DE rows
            app(TranslationService::class)->updateEnglishSource($lockedPage, [
                'title' => $title,
                'content' => $content,
                'excerpt' => $excerpt,
                'seo_title' => $lockedPage->seo_title,
                'seo_description' => $lockedPage->seo_description,
            ], Auth::id());

            $nextRevision = ($lockedPage->revisions()->max('revision_number') ?? 0) + 1;
            ContentRevision::create([
                'revisable_type' => Page::class,
                'revisable_id' => $lockedPage->id,
                'revision_number' => $nextRevision,
                'title' => $title,
                'content' => [
                    'body' => $content,
                    'excerpt' => $excerpt,
                    'og_image_path' => $ogImagePath,
                    'seo_title' => $lockedPage->seo_title,
                    'seo_description' => $lockedPage->seo_description,
                ],
                'created_by_id' => Auth::id(),
                'status' => $lockedPage->status === 'published' ? 'published' : 'draft',
            ]);

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'page_revision_restored',
                'entity_type' => Page::class,
                'entity_id' => $lockedPage->id,
                'new_data' => [
                    'restored_from_revision' => $revision->revision_number,
                    'new_revision_number' => $nextRevision,
                    'title' => $title,
                ],
                'created_at' => now(),
            ]);
        });

        return back()->with('success', "Restored content from Revision #{$revision->revision_number}.");
    }

    public function destroy(Page $page): RedirectResponse
    {
        $title = $page->title;
        $page->revisions()->delete();
        $page->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'page_deleted',
            'entity_type' => Page::class,
            'entity_id' => $page->id,
            'new_data' => ['title' => $title],
            'created_at' => now(),
        ]);

        return redirect()->route('admin.pages.index')
            ->with('success', "Page '{$title}' deleted.");
    }
}
