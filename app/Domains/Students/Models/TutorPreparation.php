<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use Database\Factories\Domains\Students\Models\TutorPreparationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorPreparation extends Model
{
    /** @use HasFactory<TutorPreparationFactory> */
    use HasFactory;

    protected $table = 'tutor_preparations';

    protected $fillable = ['student_id', 'booking_id', 'created_by', 'body'];

    protected function casts(): array
    {
        return [];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
