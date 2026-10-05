<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use Database\Factories\Domains\Students\Models\StudentErrorLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentErrorLog extends Model
{
    /** @use HasFactory<StudentErrorLogFactory> */
    use HasFactory;

    protected $table = 'student_error_logs';

    protected $fillable = ['student_id', 'booking_id', 'created_by', 'category', 'mistake', 'correction', 'notes', 'status', 'student_visible'];

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
