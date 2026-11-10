<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EstimatedActivityLifecycle
{
    /** Already under the LMS course mutex. No principal/run lock inversion and no new rows when off. */
    public function courseChanged(int $course): void
    {
        if (config('estimated_activity.mode') === 'synthetic' && app(EstimatedActivityGate::class)->isolated()) {
            DB::table('estimated_activity_context_versions')->where('course_id', $course)->increment('generation');
        }
    }

    /** Called inside the existing principal lock/transaction, before its authority changes. */
    public function revoke(Student|Administrator $principal, string $reason = 'authority_changed'): void
    {
        if (! Schema::hasTable('estimated_activity_grants')) {
            return;
        }
        $type = $principal instanceof Student ? 'student' : 'admin';
        $grants = DB::table('estimated_activity_grants')->where('principal_type', $type)->where('principal_id', $principal->id)->orderBy('run_id')->get();
        foreach ($grants as $grant) {
            DB::table('estimated_activity_runs')->where('id', $grant->run_id)->lockForUpdate()->first();
            DB::table('estimated_activity_grants')->where('id', $grant->id)->lockForUpdate()->first();
            $now = EstimatedActivityDefinition::stamp(CarbonImmutable::now('UTC'));
            DB::table('estimated_activity_grants')->where('id', $grant->id)->update(['revoked_at' => $now]);
            DB::table('estimated_activity_coverage')->where('grant_id', $grant->id)->whereNull('ended_at')->update(['ended_at' => $now, 'end_reason' => $reason]);
            DB::table('estimated_activity_streams')->where('grant_id', $grant->id)->delete();
        }
    }

    public function merge(Student $primary, Student $secondary): void
    {
        $this->revoke($primary, 'merged');
        $this->revoke($secondary, 'merged');
        if (Schema::hasTable('estimated_activity_intervals')) {
            foreach (['estimated_activity_intervals', 'estimated_activity_coverage'] as $table) {
                DB::table($table)->where('student_id', $secondary->id)->update(['student_id' => $primary->id]);
            }
        }
    }

    public function erase(Student $student): void
    {
        $this->revoke($student, 'erased');
        if (! Schema::hasTable('estimated_activity_grants')) {
            return;
        }
        foreach (['estimated_activity_intervals', 'estimated_activity_coverage'] as $table) {
            DB::table($table)->where('student_id', $student->id)->delete();
        }
        DB::table('estimated_activity_context_versions')->whereIn('course_id',
            DB::table('lms_courses')->select('id')->where('owner_student_id', $student->id))->delete();
        DB::table('estimated_activity_grants')->where('principal_type', 'student')->where('principal_id', $student->id)->delete();
    }
}
