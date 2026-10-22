<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use Illuminate\Database\Eloquent\Model;

class StudentBookmarkService
{
    public function __construct(private StudentTeachingReadModel $read) {}

    public function target(Student $student, string $kind, int $id): ?Model
    {
        return match ($kind) {
            'resource' => $this->read->resources($student, true)->firstWhere('id', $id),
            'note' => StudentBin::query()->where('student_id', $student->id)->where('student_visible', true)->find($id),
            'homework' => $this->read->shared(Homework::query(), $student)->find($id),
            default => null,
        };
    }

    public function url(string $kind, Model $target): string
    {
        return match ($kind) {
            'resource' => route('student.resources.open', $target->getKey()),
            'note' => route('student.bins.index').'#note-'.$target->getKey(),
            default => route('student.teaching.index').'#homework-'.$target->getKey(),
        };
    }
}
