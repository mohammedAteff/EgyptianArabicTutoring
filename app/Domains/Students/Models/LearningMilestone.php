<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\LearningMilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningMilestone extends Model
{
    /** @use HasFactory<LearningMilestoneFactory> */
    use HasFactory;

    protected $table = 'learning_milestones';

    protected $fillable = ['learning_plan_id', 'title', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
