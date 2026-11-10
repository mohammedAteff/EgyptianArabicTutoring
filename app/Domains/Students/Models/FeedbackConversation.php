<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\FeedbackConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackConversation extends Model
{
    /** @use HasFactory<FeedbackConversationFactory> */
    use HasFactory;

    public const TYPES = ['problem', 'suggestion', 'question', 'other'];

    public const AREAS = ['learning', 'portal', 'sessions', 'homework', 'resources', 'account', 'billing', 'other'];

    public const STATUSES = ['open', 'waiting_student', 'resolved', 'closed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'last_sequence' => 'integer', 'last_activity_at' => 'immutable_datetime', 'erased_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FeedbackEvent::class, 'conversation_id');
    }

    /**
     * @param  Builder<FeedbackConversation>  $query
     * @return Builder<FeedbackConversation>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('erased_at')->whereHas('student', function (Builder $students): Builder {
            /** @var Builder<Student> $students */
            return $students->verified()->whereNull('suspended_at')->whereNull('merged_into_student_id');
        });
    }
}
