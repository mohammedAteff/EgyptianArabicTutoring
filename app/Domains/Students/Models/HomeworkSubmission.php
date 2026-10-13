<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\HomeworkSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubmission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['homework_id', 'body', 'submission_key', 'submitted_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('Homework submission history is append-only.'));
        static::deleting(fn () => throw new \RuntimeException('Homework submission history is append-only.'));
    }

    protected static function newFactory(): HomeworkSubmissionFactory
    {
        return HomeworkSubmissionFactory::new();
    }

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Homework, $this> */
    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class, 'homework_id');
    }
}
