<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\EstimatedActivityReport;
use App\Domains\Reporting\Services\TutorWorkContext;
use App\Domains\Reporting\Services\TutorWorkReport;
use App\Domains\Students\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class IntegratedReconciliationAcceptanceTest extends TestCase
{
    use EstimatedActivityFixtures, RefreshDatabase;

    public static function examples(): array
    {
        return [
            'timer covers estimate' => ['work', [['timer', 0, '10:00:00', '11:00:00'], ['estimate', 0, '10:15:00', '10:45:00']], ['union' => 3600, 'estimated_additional' => 0, 'estimated_covered' => 1800]],
            'teaching partly covers estimate' => ['work', [['teaching', 0, '11:00:00', '12:00:00'], ['estimate', 0, '11:45:00', '12:15:00']], ['union' => 4500, 'estimated_additional' => 900, 'estimated_covered' => 900]],
            'overlapping manuals' => ['work', [['manual', 0, '10:00:00', '10:40:00'], ['manual', 0, '10:20:00', '11:00:00']], ['union' => 3600, 'gross' => 4800, 'duplicate' => 1200]],
            'two administrators one tutor' => ['work', [['estimate', 0, '10:00:00', '10:10:00'], ['estimate', 1, '10:00:00', '10:10:00']], ['union' => 600, 'estimated_shared' => 600]],
            'one student two tabs' => ['student', [['estimate', 0, '10:00:00', '10:10:00'], ['estimate', 0, '10:00:00', '10:10:00']], ['seconds' => 600, 'contributors' => 1]],
            'independent students' => ['student', [['estimate', 0, '10:00:00', '10:10:00'], ['estimate', 1, '10:00:00', '10:10:00']], ['seconds' => 1200, 'contributors' => 2]],
            'date-only two hours' => ['work', [['unplaced', 0, null, null]], ['union' => 0, 'unplaced' => 7200]],
            'adjacent intervals' => ['work', [['timer', 0, '10:00:00', '11:00:00'], ['timer', 0, '11:00:00', '12:00:00']], ['union' => 7200, 'duplicate' => 0]],
            'nested triple overlap' => ['work', [['timer', 0, '10:00:00', '12:00:00'], ['manual', 0, '10:30:00', '11:00:00'], ['estimate', 0, '10:45:00', '11:15:00']], ['union' => 7200, 'estimated_additional' => 0]],
            'microsecond gaps remain gaps' => ['student', [['estimate', 0, '10:00:00.500000', '10:00:15.500000'], ['estimate', 0, '10:00:16.000000', '10:00:31.000000']], ['seconds' => 30]],
            'fractional estimate preserves teaching seconds' => ['work', [['teaching', 0, '10:00:00', '11:00:00'], ['estimate', 0, '10:15:00.500000', '10:45:00.500000']], ['union' => 3600, 'estimated_additional' => 0, 'estimated_shared' => 1800]],
            'empty upgraded workspace' => ['work', [], ['union' => 0, 'unplaced' => 0]],
        ];
    }

    #[DataProvider('examples')]
    public function test_independent_expected_values_match_integrated_projection(string $domain, array $rows, array $expected): void
    {
        $principals = $domain === 'student'
            ? [Student::factory()->verified()->create(), Student::factory()->verified()->create()]
            : [Administrator::factory()->create(), Administrator::factory()->create()];
        $this->activityRun(...$principals);
        $this->travel(1)->hours();
        $ids = [];
        foreach ($rows as [$source, $actorIndex, $start, $end]) {
            $actor = $principals[$actorIndex];
            $start = $start === null ? null : '2026-11-04 '.$start;
            $end = $end === null ? null : '2026-11-04 '.$end;
            if ($source === 'estimate') {
                $id = $this->activityInterval($actor, $start, $end, $domain === 'student' ? 'lms' : 'feedback');
            } elseif ($source === 'teaching') {
                $id = Booking::factory()->create(['student_id' => null, 'status' => 'completed', 'start_at_utc' => $start, 'end_at_utc' => $end])->id;
            } else {
                $entry = TutorWorkEntry::factory()->create(['created_by' => $actor->id, 'origin' => $source === 'unplaced' ? 'manual' : $source,
                    'placement' => $source === 'unplaced' ? 'unplaced' : 'placed', 'unplaced_seconds' => $source === 'unplaced' ? 7200 : null,
                    'work_date' => '2026-11-04', 'acceptance' => 'accepted']);
                $id = $entry->id;
                if ($source !== 'unplaced') {
                    DB::table('tutor_work_intervals')->insert(['entry_id' => $id, 'workspace_id' => $entry->workspace_id, 'started_at' => $start, 'ended_at' => $end]);
                }
            }
            $ids[] = ['source' => $source, 'id' => $id, 'actor' => $actor->id, 'start_utc' => $start, 'end_utc' => $end];
        }

        $actual = $domain === 'student' ? app(EstimatedActivityReport::class)->student($this->activityPeriod())
            : app(TutorWorkReport::class)->summary(TutorWorkContext::fromRequest(request()->duplicate(['range' => 'today', 'timezone' => 'UTC']), $principals[0]->id));

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $actual[$key], $key);
        }
        file_put_contents($this->acceptanceOutputPath('numerical-'.sha1(json_encode([$domain, $rows])).'.json'), json_encode([
            'domain' => $domain, 'fixtures' => $ids, 'timezone' => 'UTC', 'period' => '2026-11-04', 'cutoff' => '2026-11-04T13:00:00Z',
            'expected' => $expected, 'actual' => array_intersect_key($actual, $expected), 'difference' => array_map(fn (string $key): int => $actual[$key] - $expected[$key], array_keys($expected)),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
