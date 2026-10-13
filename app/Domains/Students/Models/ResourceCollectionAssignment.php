<?php

namespace App\Domains\Students\Models;

use App\Domains\Resources\Models\ResourceCollection;
use Database\Factories\Domains\Students\Models\ResourceCollectionAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceCollectionAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'resource_collection_id', 'student_visible', 'created_by'];

    protected static function newFactory(): ResourceCollectionAssignmentFactory
    {
        return ResourceCollectionAssignmentFactory::new();
    }

    protected function casts(): array
    {
        return ['student_visible' => 'boolean'];
    }

    /** @return BelongsTo<ResourceCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(ResourceCollection::class, 'resource_collection_id');
    }
}
