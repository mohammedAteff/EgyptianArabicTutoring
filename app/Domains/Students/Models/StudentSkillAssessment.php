<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\StudentSkillAssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSkillAssessment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['student_id', 'learning_skill_id', 'booking_id', 'state', 'notes', 'student_visible', 'assessed_at', 'created_by'];

    protected static function newFactory(): StudentSkillAssessmentFactory
    {
        return StudentSkillAssessmentFactory::new();
    }

    protected function casts(): array
    {
        return ['student_visible' => 'boolean', 'assessed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningSkill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(LearningSkill::class, 'learning_skill_id');
    }
}
