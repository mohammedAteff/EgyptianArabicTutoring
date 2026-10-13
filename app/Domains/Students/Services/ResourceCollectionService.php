<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCollection;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\ResourceCollectionAssignment;
use App\Domains\Students\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResourceCollectionService
{
    public function __construct(private TeachingRecordService $records, private AuditLogService $audit) {}

    /** @param array<string, mixed> $data */
    public function save(?ResourceCollection $collection, array $data, int $actorId): ResourceCollection
    {
        return DB::transaction(function () use ($collection, $data, $actorId): ResourceCollection {
            $collection = $collection ? ResourceCollection::query()->lockForUpdate()->findOrFail($collection->id) : new ResourceCollection;
            $ids = $data['resource_ids'];
            unset($data['resource_ids']);
            if (Resource::query()->published()->whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['resource_ids' => 'Choose published resources only.']);
            }
            $collection->fill($data)->save();
            $items = [];
            foreach ($ids as $position => $id) {
                $items[$id] = ['sort_order' => $position];
            }
            $collection->resources()->sync($items);
            $this->audit->log('resource_collection_saved', ResourceCollection::class, $collection->id, null, ['resource_ids' => $ids], $actorId);

            return $collection;
        }, 3);
    }

    public function assign(Student $student, ResourceCollection $collection, int $actorId): void
    {
        DB::transaction(function () use ($student, $collection, $actorId): void {
            $student = $this->records->lockStudent($student->id);
            $collection = ResourceCollection::query()->published()->lockForUpdate()->findOrFail($collection->id);
            $assignment = ResourceCollectionAssignment::query()->firstOrCreate(['student_id' => $student->id, 'resource_collection_id' => $collection->id], ['created_by' => $actorId, 'student_visible' => true]);
            foreach ($collection->resources()->published()->where('student_discoverable', true)->get() as $resource) {
                ResourceAssignment::query()->firstOrCreate(['student_id' => $student->id, 'resource_id' => $resource->id, 'booking_id' => null], ['created_by' => $actorId, 'student_visible' => true, 'instructions' => $collection->description]);
            }
            if ($assignment->wasRecentlyCreated) {
                $this->audit->log('resource_collection_assigned', Student::class, $student->id, null, ['collection_id' => $collection->id], $actorId);
            }
        }, 3);
    }
}
