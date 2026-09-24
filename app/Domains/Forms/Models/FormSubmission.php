<?php

namespace App\Domains\Forms\Models;

use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSubmission extends Model
{
    protected $fillable = ['form_version_id', 'student_id', 'status', 'submitted_at', 'submission_revision'];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'submission_revision' => 'integer'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FormAnswer::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(FormSubmissionRevision::class)->orderBy('revision_number');
    }
}
