<?php

namespace App\Http\Controllers\Admin;

use App\Domains\CMS\Models\EntityTranslationRevision;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\TranslationService;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranslationController extends Controller
{
    public function __construct(
        protected TranslationService $translationService
    ) {}

    /**
     * Resolve model instance by entity type and ID.
     */
    protected function resolveEntity(string $entityType, int|string $id): Model
    {
        return match ($entityType) {
            'resource', 'resources' => Resource::findOrFail($id),
            'game', 'games' => Game::findOrFail($id),
            'page', 'pages' => Page::findOrFail($id),
            'faq', 'faqs' => Faq::findOrFail($id),
            'category', 'categories', 'resource-category', 'resource-categories' => ResourceCategory::findOrFail($id),
            default => abort(404, "Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Save localized draft for French or German.
     */
    public function saveDraft(Request $request, string $entityType, int|string $id, string $locale): RedirectResponse|JsonResponse
    {
        $entity = $this->resolveEntity($entityType, $id);

        if (! in_array($locale, ['fr', 'de'], true)) {
            abort(400, 'Invalid locale for translation.');
        }

        $translatableData = $this->extractTranslatableData($request, $entity->getEntityType());
        $reconcileToRevision = $request->filled('reconcile_to_revision') ? (int) $request->input('reconcile_to_revision') : null;

        $draft = $this->translationService->saveDraft($entity, $locale, $translatableData, $reconcileToRevision);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'draft' => $draft]);
        }

        return back()->with('success', strtoupper($locale).' draft saved successfully.');
    }

    /**
     * Explicit Publish Action: atomically archives prior live translation and publishes draft.
     */
    public function publish(Request $request, string $entityType, int|string $id, string $locale): RedirectResponse|JsonResponse
    {
        $entity = $this->resolveEntity($entityType, $id);

        if (! in_array($locale, ['fr', 'de'], true)) {
            abort(400, 'Invalid locale for translation.');
        }

        // If form fields were also submitted with the publish action, save them first
        if ($request->hasAny(['title', 'name', 'question'])) {
            $translatableData = $this->extractTranslatableData($request, $entity->getEntityType());
            $reconcileToRevision = $request->filled('reconcile_to_revision') ? (int) $request->input('reconcile_to_revision') : null;
            if (! empty(array_filter($translatableData))) {
                $this->translationService->saveDraft($entity, $locale, $translatableData, $reconcileToRevision);
            }
        }

        try {
            $published = $this->translationService->publishTranslation($entity, $locale);
        } catch (\DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'published' => $published]);
        }

        return back()->with('success', strtoupper($locale).' translation published successfully.');
    }

    /**
     * View reconciliation diff between outdated source revision and current English revision.
     */
    public function reconcile(string $entityType, int|string $id, string $locale): View
    {
        $entity = $this->resolveEntity($entityType, $id);
        $draft = $entity->draftTranslation($locale);
        $currentRevision = $entity->currentSourceRevision();
        $sourceRevision = null;

        if ($draft && $draft->source_revision_id) {
            $sourceRevision = EntityTranslationRevision::where('entity_type', $entity->getEntityType())
                ->where('entity_id', $entity->getKey())
                ->where('revision_number', $draft->source_revision_id)
                ->first();
        }

        return view('admin.translations.reconcile', [
            'entity' => $entity,
            'entityType' => $entityType,
            'locale' => $locale,
            'draft' => $draft,
            'sourceRevision' => $sourceRevision,
            'currentRevision' => $currentRevision,
        ]);
    }

    /**
     * Extract validated translatable data from request based on entity type.
     */
    protected function extractTranslatableData(Request $request, string $type): array
    {
        return match ($type) {
            'resource' => $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'short_description' => ['nullable', 'string', 'max:2000'],
                'full_description' => ['nullable', 'string'],
            ]),
            'game' => $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
            ]),
            'page' => $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'content' => ['required', 'string'],
                'excerpt' => ['nullable', 'string', 'max:1000'],
                'seo_title' => ['nullable', 'string', 'max:255'],
                'seo_description' => ['nullable', 'string', 'max:500'],
            ]),
            'faq' => $request->validate([
                'question' => ['required', 'string', 'max:500'],
                'answer' => ['required', 'string'],
            ]),
            'category' => $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
            ]),
            default => [],
        };
    }
}
