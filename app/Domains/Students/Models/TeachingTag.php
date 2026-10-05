<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use Database\Factories\Domains\Students\Models\TeachingTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingTag extends Model
{
    /** @use HasFactory<TeachingTagFactory> */
    use HasFactory;

    protected $table = 'teaching_tags';

    protected $fillable = ['student_id', 'booking_id', 'created_by', 'label', 'student_visible'];

    protected function casts(): array
    {
        return ['student_visible' => 'boolean'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
