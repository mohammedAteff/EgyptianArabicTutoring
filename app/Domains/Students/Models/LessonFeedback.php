<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use Database\Factories\Domains\Students\Models\LessonFeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonFeedback extends Model
{
    /** @use HasFactory<LessonFeedbackFactory> */
    use HasFactory;

    protected $table = 'lesson_feedback';

    protected $fillable = ['student_id', 'booking_id', 'rating', 'comment'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
