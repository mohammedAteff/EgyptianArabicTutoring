<?php

namespace App\Domains\Lms\Models;

use App\Domains\Booking\Models\Booking;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $access_grant_id
 * @property int|null $booking_id
 * @property string $status
 */
class LearningAssignment extends LmsModel
{
    protected $table = 'lms_learning_assignments';

    protected $hidden = ['instructions'];

    /** @return BelongsTo<AccessGrant, $this> */
    public function accessGrant(): BelongsTo
    {
        return $this->belongsTo(AccessGrant::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
