<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\LmsCourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/** @property int $id
 * @property string $kind
 * @property int|null $owner_student_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $lock_version
 */
class Course extends LmsModel
{
    use HasFactory;

    protected $table = 'lms_courses';

    protected static function newFactory(): LmsCourseFactory
    {
        return LmsCourseFactory::new();
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'lock_version' => 'integer'];
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<Student, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'owner_student_id');
    }

    /** @return HasOne<AccessRule, $this> */
    public function accessRule(): HasOne
    {
        return $this->hasOne(AccessRule::class);
    }

    /** @return HasMany<AccessGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
