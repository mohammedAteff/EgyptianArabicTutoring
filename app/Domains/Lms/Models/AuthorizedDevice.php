<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\AuthorizedDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $student_id
 * @property string $token_hash
 * @property string $label
 * @property string $status
 * @property Carbon $last_seen_at
 * @property Carbon|null $revoked_at
 */
class AuthorizedDevice extends Model
{
    /** @use HasFactory<AuthorizedDeviceFactory> */
    use HasFactory;

    protected $table = 'lms_authorized_devices';

    protected $guarded = ['*'];

    protected $hidden = ['token_hash'];

    protected static function newFactory(): AuthorizedDeviceFactory
    {
        return AuthorizedDeviceFactory::new();
    }

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
