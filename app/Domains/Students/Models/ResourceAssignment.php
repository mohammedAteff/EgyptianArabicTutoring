<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use Database\Factories\Domains\Students\Models\ResourceAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAssignment extends Model
{
    /** @use HasFactory<ResourceAssignmentFactory> */
    use HasFactory;

    protected $table = 'resource_assignments';

    protected $fillable = ['student_id', 'booking_id', 'created_by', 'resource_id', 'instructions', 'student_visible', 'reviewed_at'];

    protected function casts(): array
    {
        return ['student_visible' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<\App\Domains\Resources\Models\Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Resources\Models\Resource::class);
    }
}
