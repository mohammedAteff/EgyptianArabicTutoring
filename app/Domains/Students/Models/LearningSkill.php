<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\LearningSkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningSkill extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'active', 'sort_order'];

    protected static function newFactory(): LearningSkillFactory
    {
        return LearningSkillFactory::new();
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }
}
