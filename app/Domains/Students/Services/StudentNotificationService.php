<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Policies\StudentTeachingPolicy;
use Illuminate\Support\Facades\DB;

class StudentNotificationService
{
    /** @param list<array{key: string, type: string, title: string, message: string, link: string}> $events */
    public function synchronize(Student $student, array $events): void
    {
        DB::transaction(function () use ($student, $events): void {
            $locked = Student::query()->lockForUpdate()->find($student->id);
            if (! $locked || ! app(StudentTeachingPolicy::class)->usePortal($locked)) {
                return;
            }
            $now = now('UTC');
            $rows = array_map(fn (array $event): array => [
                'student_id' => $locked->id, 'deduplication_key' => hash('sha256', $event['key']),
                'type' => $event['type'], 'title' => $event['title'], 'message' => $event['message'],
                'link' => $event['link'], 'created_at' => $now, 'updated_at' => $now,
            ], $events);
            if ($rows !== []) {
                StudentNotification::query()->insertOrIgnore($rows);
            }
        }, 3);
    }
}
