<?php

namespace App\Domains\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['form_submission_id', 'revision_number', 'snapshot_answers', 'submitted_at', 'created_at'];

    protected function casts(): array
    {
        return ['snapshot_answers' => 'array', 'submitted_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Form submission revisions are append-only.'));
        static::deleting(fn (): never => throw new \LogicException('Form submission revisions are append-only.'));
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }
}
