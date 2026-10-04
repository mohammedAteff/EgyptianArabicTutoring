<?php

namespace App\Domains\Booking\Models;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Resources\Models\Resource as LibraryResource;
use Database\Factories\Domains\Booking\Models\LessonMaterialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonMaterial extends Model
{
    /** @use HasFactory<LessonMaterialFactory> */
    use HasFactory;

    public const KINDS = ['private_file', 'resource', 'external_link', 'recording'];

    protected $fillable = ['booking_id', 'created_by', 'kind', 'title', 'description', 'resource_id', 'disk', 'path', 'url', 'student_visible', 'sort_order', 'withdrawn_at'];

    protected $hidden = ['disk', 'path', 'url'];

    protected function casts(): array
    {
        return ['student_visible' => 'boolean', 'sort_order' => 'integer', 'withdrawn_at' => 'datetime'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<LibraryResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(LibraryResource::class);
    }

    /** @return BelongsTo<Administrator, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'created_by');
    }

    /** @param Builder<LessonMaterial> $query
     * @return Builder<LessonMaterial>
     */
    public function scopeVisibleToStudent(Builder $query): Builder
    {
        return $query->where('student_visible', true)->whereNull('withdrawn_at')
            ->whereHas('booking', fn (Builder $bookings) => $bookings->where(function (Builder $eligible): void {
                $eligible->where('status', 'completed')->orWhere(function (Builder $past): void {
                    $past->where('status', 'confirmed')->where('end_at_utc', '<=', now('UTC'));
                });
            }))
            ->where(fn (Builder $materials) => $materials->where('kind', '!=', 'resource')
                ->orWhereHas('resource', fn (Builder $resources) => $resources->published()));
    }
}
