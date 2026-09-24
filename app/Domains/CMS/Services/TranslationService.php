<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\EntityTranslationRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TranslationService
{
    public function __construct(private RichTextSanitizer $sanitizer) {}

    /**
     * Update English source content and handle revisioning + staleness marking.
     */
    public function updateEnglishSource(
        Model $entity,
        array $translatableData,
        ?int $adminId = null
    ): EntityTranslationRevision {
        $translatableData = $this->sanitizeTranslatableData($entity, $translatableData);

        return DB::transaction(function () use ($entity, $translatableData, $adminId) {
            $entityId = $entity->getKey();
            $entity = $entity->newQuery()->whereKey($entityId)->lockForUpdate()->firstOrFail();
            $entityType = $entity->getEntityType();

            // Lock entity revisions for update to prevent concurrent race conditions
            $latestRevision = EntityTranslationRevision::where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->orderByDesc('revision_number')
                ->lockForUpdate()
                ->first();

            $hasChanged = true;
            if ($latestRevision) {
                $hasChanged = $this->hasTranslatableContentChanged($latestRevision, $translatableData);
            }

            if (! $hasChanged && $latestRevision) {
                // Ensure English translation row exists and points to latest revision
                $this->upsertEnglishTranslation($entity, $translatableData, $latestRevision->revision_number);

                return $latestRevision;
            }

            $nextRevisionNumber = $latestRevision ? $latestRevision->revision_number + 1 : 1;

            $newRevision = EntityTranslationRevision::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'revision_number' => $nextRevisionNumber,
                'locale' => 'en',
                'title' => $translatableData['title'] ?? ($translatableData['name'] ?? ($translatableData['question'] ?? null)),
                'description' => $translatableData['short_description'] ?? ($translatableData['description'] ?? null),
                'content' => $translatableData,
                'created_by' => $adminId,
                'created_at' => now(),
            ]);

            // Update or create English translation pointing to new revision
            $this->upsertEnglishTranslation($entity, $translatableData, $nextRevisionNumber);

            // Mark published French and German translations as stale
            $transClass = $entity->getTranslationModelClass();
            $transClass::where($entity->getForeignKey(), $entityId)
                ->whereIn('locale', ['fr', 'de'])
                ->where('status', 'published')
                ->update(['status' => 'stale']);

            return $newRevision;
        });
    }

    /**
     * Check if translatable content has actually changed compared to snapshot.
     */
    protected function hasTranslatableContentChanged(EntityTranslationRevision $revision, array $newData): bool
    {
        $oldContent = $revision->content ?? [];

        foreach ($newData as $key => $newVal) {
            $oldVal = $oldContent[$key] ?? null;
            if (trim((string) $oldVal) !== trim((string) $newVal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save an English translation row as published.
     */
    public function upsertEnglishTranslation(Model $entity, array $translatableData, int $revisionNumber): Model
    {
        $translatableData = $this->sanitizeTranslatableData($entity, $translatableData);
        $transClass = $entity->getTranslationModelClass();
        $foreignKey = $entity->getForeignKey();

        $enTrans = $transClass::where($foreignKey, $entity->getKey())
            ->where('locale', 'en')
            ->whereIn('status', ['published', 'stale'])
            ->first();

        $attributes = array_merge($translatableData, [
            $foreignKey => $entity->getKey(),
            'locale' => 'en',
            'source_revision_id' => $revisionNumber,
            'status' => 'published',
            'published_at' => $enTrans?->published_at ?? now(),
        ]);

        if ($enTrans) {
            $enTrans->update($attributes);

            return $enTrans;
        }

        return $transClass::create($attributes);
    }

    /**
     * Save localized draft for French or German without publishing.
     */
    public function saveDraft(
        Model $entity,
        string $locale,
        array $translatableData,
        ?int $sourceRevisionId = null
    ): Model {
        if (! in_array($locale, ['fr', 'de'], true)) {
            throw new \InvalidArgumentException('Drafts can only be saved for localized languages (fr, de).');
        }

        $translatableData = $this->sanitizeTranslatableData($entity, $translatableData);

        return DB::transaction(function () use ($entity, $locale, $translatableData, $sourceRevisionId) {
            $entity = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $transClass = $entity->getTranslationModelClass();
            $foreignKey = $entity->getForeignKey();

            $draft = $transClass::where($foreignKey, $entity->getKey())
                ->where('locale', $locale)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();

            // If sourceRevisionId is explicitly provided (reconciliation save), validate and advance revision
            if ($sourceRevisionId !== null) {
                $validRevision = EntityTranslationRevision::where('entity_type', $entity->getEntityType())
                    ->where('entity_id', $entity->getKey())
                    ->where('revision_number', $sourceRevisionId)
                    ->exists();

                if (! $validRevision) {
                    throw new \InvalidArgumentException("Source revision #{$sourceRevisionId} does not belong to this {$entity->getEntityType()}.");
                }

                $targetRevision = $sourceRevisionId;
            } else {
                // Retain existing draft revision reference if present, otherwise use current English revision
                $targetRevision = $draft?->source_revision_id ?? ($entity->currentSourceRevision()?->revision_number ?? 1);
            }

            $data = array_merge($translatableData, [
                $foreignKey => $entity->getKey(),
                'locale' => $locale,
                'source_revision_id' => $targetRevision,
                'status' => 'draft',
            ]);

            if ($draft) {
                $draft->update($data);

                return $draft;
            }

            return $transClass::create($data);
        });
    }

    /**
     * Explicit Publish Action: atomically archives previous live translation and publishes draft.
     * Enforces that the draft must be reconciled to the latest English revision before publishing.
     */
    public function publishTranslation(Model $entity, string $locale): Model
    {
        return DB::transaction(function () use ($entity, $locale) {
            $entity = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $transClass = $entity->getTranslationModelClass();
            $foreignKey = $entity->getForeignKey();

            $draft = $transClass::where($foreignKey, $entity->getKey())
                ->where('locale', $locale)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();

            if (! $draft) {
                throw new \RuntimeException("No active draft translation found to publish for {$locale}.");
            }

            $currentEnglishRevision = $entity->currentSourceRevision()?->revision_number ?? 1;

            // Reject publish if draft is based on an outdated English revision
            if ((int) $draft->source_revision_id < (int) $currentEnglishRevision) {
                throw new \DomainException(
                    "Cannot publish outdated {$locale} draft (based on revision #{$draft->source_revision_id}, current is #{$currentEnglishRevision}). Please reconcile changes before publishing."
                );
            }

            // Archive any prior live translation (published or stale)
            $transClass::where($foreignKey, $entity->getKey())
                ->where('locale', $locale)
                ->whereIn('status', ['published', 'stale'])
                ->update(['status' => 'archived']);

            // Transition draft to published maintaining its reconciled source_revision_id
            $draft->update([
                'status' => 'published',
                'source_revision_id' => $draft->source_revision_id,
                'published_at' => now(),
            ]);

            return $draft;
        });
    }

    /**
     * Check if a draft is based on an outdated source revision.
     */
    public function draftRequiresReconciliation(Model $entity, string $locale): bool
    {
        $draft = $entity->draftTranslation($locale);
        if (! $draft) {
            return false;
        }

        $currentRevisionNumber = $entity->currentSourceRevision()?->revision_number ?? 1;

        return (int) $draft->source_revision_id < (int) $currentRevisionNumber;
    }

    /**
     * Sanitize rich page content at the service boundary so controller and
     * programmatic translation writes share the same HTML trust policy.
     *
     * @param  array<string, mixed>  $translatableData
     * @return array<string, mixed>
     */
    private function sanitizeTranslatableData(Model $entity, array $translatableData): array
    {
        if ($entity->getEntityType() === 'page' && array_key_exists('content', $translatableData)) {
            $translatableData['content'] = $this->sanitizer->sanitize((string) $translatableData['content']);
        }

        return $translatableData;
    }
}
