<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use Database\Factories\Domains\Students\Models\HomeworkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Homework extends Model
{
    /** @use HasFactory<HomeworkFactory> */
    use HasFactory;

    protected $table = 'homeworks';

    protected $fillable = ['student_id', 'booking_id', 'created_by', 'title', 'instructions', 'assigned_date', 'due_date', 'status', 'student_visible', 'feedback', 'student_response', 'url', 'resource_id', 'lesson_material_id'];

    protected function casts(): array
    {
        return ['student_visible' => 'boolean', 'assigned_date' => 'date', 'due_date' => 'date'];
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

    /** @return BelongsTo<LessonMaterial, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(LessonMaterial::class, 'lesson_material_id');
    }
}
