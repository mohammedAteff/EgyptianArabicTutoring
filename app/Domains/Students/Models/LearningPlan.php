<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\LearningPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningPlan extends Model
{
    /** @use HasFactory<LearningPlanFactory> */
    use HasFactory;

    protected $table = 'learning_plans';

    protected $fillable = ['student_id', 'created_by', 'title', 'goals', 'focus_areas', 'current_level', 'notes', 'status', 'start_date', 'student_visible'];

    protected function casts(): array
    {
        return ['student_visible' => 'boolean', 'start_date' => 'date'];
    }

    /** @return HasMany<LearningMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(LearningMilestone::class)->orderBy('id');
    }
}
