<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\LearningMilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningMilestone extends Model
{
    /** @use HasFactory<LearningMilestoneFactory> */
    use HasFactory;

    protected $table = 'learning_milestones';

    protected $fillable = ['learning_plan_id', 'title', 'status', 'completed_at'];

    /** @return BelongsTo<LearningPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(LearningPlan::class, 'learning_plan_id');
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
