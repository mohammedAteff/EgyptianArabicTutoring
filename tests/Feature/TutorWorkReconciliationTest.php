<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Reporting\Services\TutorWorkContext;
use App\Domains\Reporting\Services\TutorWorkExport;
use App\Domains\Reporting\Services\TutorWorkReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TutorWorkReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): Administrator
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 20:00:00', 'UTC'));
        $owner = Administrator::factory()->create(['role' => 'super_admin']);
        $this->actingAs($owner, 'web');

        return $owner;
    }

    private function context(Administrator $owner, array $filters = []): TutorWorkContext
    {
        return TutorWorkContext::fromRequest(request()->duplicate($filters + ['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'timezone' => 'UTC']), $owner->id);
    }

    private function placed(Administrator $owner, string $start, string $end, string $origin = 'timer', string $category = 'lesson_preparation', string $state = 'accepted'): TutorWorkEntry
    {
        $entry = TutorWorkEntry::factory()->create(['created_by' => $owner->id, 'origin' => $origin, 'category' => $category, 'placement' => 'placed', 'unplaced_seconds' => null, 'work_date' => substr($start, 0, 10), 'acceptance' => $state, 'description' => '=PRIVATE DESCRIPTION', 'original_declaration' => ['private' => 'ORIGINAL PRIVATE']]);
        DB::table('tutor_work_intervals')->insert(['entry_id' => $entry->id, 'workspace_id' => $entry->workspace_id, 'started_at' => $start, 'ended_at' => $end]);

        return $entry;
    }

    public static function intervalCases(): array
    {
        return [
            'manual beats timer' => [[['10:00', '11:00', 'timer', 'feedback'], ['10:30', '11:30', 'manual', 'lesson_preparation']], 7200, 5400, ['feedback' => 1800, 'lesson_preparation' => 3600]],
            'adjacent' => [[['10:00', '11:00', 'timer', 'feedback'], ['11:00', '12:00', 'timer', 'feedback']], 7200, 7200, ['feedback' => 7200]],
            'nested' => [[['10:00', '12:00', 'timer', 'feedback'], ['10:30', '11:00', 'manual', 'lesson_preparation']], 9000, 7200, ['feedback' => 5400, 'lesson_preparation' => 1800]],
            'triple duplicate source duration' => [[['10:00', '11:00', 'timer', 'feedback'], ['10:00', '11:00', 'timer', 'feedback'], ['10:00', '11:00', 'timer', 'feedback']], 10800, 3600, ['feedback' => 3600]],
            'same priority category conflict' => [[['10:00', '11:00', 'manual', 'feedback'], ['10:30', '11:30', 'manual', 'lesson_preparation']], 7200, 5400, ['feedback' => 1800, 'unresolved_overlap' => 1800, 'lesson_preparation' => 1800]],
            'pause gap' => [[['10:00', '10:20', 'timer', 'feedback'], ['10:30', '11:00', 'timer', 'feedback']], 3000, 3000, ['feedback' => 3000]],
        ];
    }

    #[DataProvider('intervalCases')]
    public function test_reconciles_numeric_interval_examples(array $intervals, int $gross, int $union, array $categories): void
    {
        $owner = $this->owner();
        foreach ($intervals as [$start, $end, $origin, $category]) {
            $this->placed($owner, '2026-11-01 '.$start.':00', '2026-11-01 '.$end.':00', $origin, $category);
        }
        $summary = app(TutorWorkReport::class)->summary($this->context($owner));

        $this->assertSame($gross, $summary['gross']);
        $this->assertSame($union, $summary['union']);
        $this->assertSame($gross - $union, $summary['duplicate']);
        $this->assertSame($categories, $summary['categories']);
        $this->assertSame($union, array_sum($summary['categories']));
    }

    public function test_shared_workspace_combines_teaching_once_for_all_super_admins_and_reconciles_before_filters(): void
    {
        $owner = $this->owner();
        $this->placed($owner, '2026-11-01 10:30:00', '2026-11-01 11:30:00');
        Booking::factory()->create(['student_id' => null, 'status' => 'completed', 'start_at_utc' => '2026-11-01 10:00:00', 'end_at_utc' => '2026-11-01 11:00:00']);
        Booking::factory()->create(['student_id' => null, 'status' => 'completed', 'start_at_utc' => '2026-11-01 09:00:00', 'end_at_utc' => '2026-11-01 08:00:00']);
        $read = app(TutorWorkReport::class);
        config(['tutor_work.tutor_administrator_id' => 999999]);
        $summary = $read->summary($this->context($owner));
        $this->assertSame(7200, $summary['gross']);
        $this->assertSame(5400, $summary['union']);
        $this->assertSame(['teaching' => 3600, 'lesson_preparation' => 1800], $summary['categories']);
        $filtered = $read->summary($this->context($owner, ['source' => 'timer', 'category' => 'lesson_preparation']));
        $this->assertSame(1800, $filtered['allocated']);
        $this->assertSame(7200, $filtered['gross']);
        $this->assertSame(1800, $filtered['duplicate']);
        $this->assertSame(1800, $summary['cross_source_disagreement']);
        $other = Administrator::factory()->create();
        $this->assertSame($this->context($owner)->workspace, $this->context($other)->workspace);
        $this->assertSame(5400, $read->summary($this->context($other))['union']);
    }

    public function test_clips_intersections_crossing_bounds_and_splits_at_local_midnight_and_dst(): void
    {
        $owner = $this->owner();
        $this->placed($owner, '2026-10-31 23:00:00', '2026-11-01 01:00:00');
        $this->placed($owner, '2026-11-03 23:30:00', '2026-11-04 01:00:00');
        $read = app(TutorWorkReport::class);
        $context = $this->context($owner);
        $summary = $read->summary($context);
        $this->assertSame(5400, $summary['union']);
        $this->assertSame(['2026-11-01' => ['allocated' => 3600], '2026-11-03' => ['allocated' => 1800]], $summary['buckets']);
        $this->assertSame('2026-11-01 00:00:00', iterator_to_array($read->allocations($context))[0]['started_at']);
        $this->placed($owner, '2026-10-24 22:00:00', '2026-10-25 23:00:00');
        $berlin = $this->context($owner, ['start_date' => '2026-10-25', 'end_date' => '2026-10-25', 'timezone' => 'Europe/Berlin']);
        $this->assertSame(90000, $read->summary($berlin)['union']);
    }

    public function test_unplaced_retains_literal_date_timezone_and_never_enters_union_or_state_filtered_totals(): void
    {
        $owner = $this->owner();
        $this->placed($owner, '2026-11-01 09:00:00', '2026-11-01 10:00:00');
        $this->placed($owner, '2026-11-01 09:00:00', '2026-11-01 11:00:00', state: 'needs_review');
        $this->placed($owner, '2026-11-01 11:00:00', '2026-11-01 12:00:00', state: 'excluded');
        TutorWorkEntry::factory()->create(['created_by' => $owner->id, 'work_date' => '2026-11-01', 'timezone' => 'Pacific/Kiritimati', 'unplaced_seconds' => 7200]);
        $summary = app(TutorWorkReport::class)->summary($this->context($owner, ['timezone' => 'America/Los_Angeles', 'state' => 'excluded']));
        $this->assertSame(3600, $summary['union']);
        $this->assertSame(7200, $summary['unplaced']);
        $this->assertSame(1, $summary['review_count']);
        $this->assertSame(7200, $summary['buckets']['2026-11-01']['unplaced']);
    }

    public function test_allocations_keep_stable_winner_and_all_participating_evidence(): void
    {
        $owner = $this->owner();
        $first = $this->placed($owner, '2026-11-01 10:00:00', '2026-11-01 11:00:00', 'manual');
        $second = $this->placed($owner, '2026-11-01 10:00:00', '2026-11-01 11:00:00', 'manual');
        $allocation = iterator_to_array(app(TutorWorkReport::class)->allocations($this->context($owner)))[0];
        $this->assertSame('manual:'.$first->id, $allocation['contribution']);
        $this->assertStringContainsString('manual:'.$second->id, $allocation['references']);
        $this->assertFalse($allocation['conflict']);
    }

    #[TestWith(['summary'])]
    #[TestWith(['records'])]
    #[TestWith(['allocations'])]
    public function test_csv_and_xlsx_export_all_rows_numeric_seconds_metadata_and_no_private_text(string $dataset): void
    {
        $owner = $this->owner();
        for ($index = 0; $index < 23; $index++) {
            $this->placed($owner, '2026-11-01 '.sprintf('%02d:00:00', $index), '2026-11-01 '.sprintf('%02d:30:00', $index));
        }
        $filters = ['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'timezone' => 'UTC', 'dataset' => $dataset];
        $csv = $this->get(route('admin.tutor-work.export', $filters + ['format' => 'csv']))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->streamedContent();
        foreach (['PRIVATE DESCRIPTION', 'ORIGINAL PRIVATE', 'description', 'password', 'correction reason'] as $private) {
            $this->assertStringNotContainsString($private, $csv);
        }
        $this->assertStringContainsString('Numeric seconds', $csv);
        $this->assertStringContainsString('tutor-work-v3-estimated', $csv);
        $response = $this->get(route('admin.tutor-work.export', $filters + ['format' => 'xlsx']))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->assertHeader('Cache-Control', 'no-store, private');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('Numeric seconds', $sheet->getCell('L1')->getValue());
            $this->assertSame('n', $sheet->getCell('L2')->getDataType());
            $this->assertGreaterThan(0, $sheet->getCell('L2')->getValue());
            if ($dataset !== 'summary') {
                $this->assertSame(24, $sheet->getHighestRow());
            }
            $this->assertSame('workspace_id', $sheet->getCell('U1')->getValue());
            ob_start();
            try {
                $response->baseResponse->sendContent();
            } finally {
                ob_end_clean();
            }
            $this->assertFileDoesNotExist($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_export_filter_parity_and_xlsx_capacity_recovery_keep_scope_and_dataset(): void
    {
        $owner = $this->owner();
        $this->placed($owner, '2026-11-01 14:00:00', '2026-11-01 15:00:00', 'timer', 'feedback');
        $this->placed($owner, '2026-11-01 14:30:00', '2026-11-01 15:30:00', 'manual', 'lesson_preparation');
        $context = $this->context($owner, ['source' => 'timer']);
        $rows = iterator_to_array(app(TutorWorkExport::class)->rows($context, 'allocations'));
        $this->assertSame(1800, array_sum(array_column($rows, 11)));
        $this->assertSame(1800, app(TutorWorkReport::class)->summary($context)['allocated']);
        $this->mock(ExportService::class)->shouldReceive('export')->once()->andThrow(ValidationException::withMessages(['format' => 'Use CSV or a narrower range.']));
        $this->get(route('admin.tutor-work.export', $context->filters() + ['format' => 'xlsx']))->assertRedirect(route('admin.tutor-work.index', $context->filters()))->assertSessionHasErrors('format');
    }

    public function test_report_query_count_does_not_grow_per_work_record(): void
    {
        $owner = $this->owner();
        $this->placed($owner, '2026-11-01 10:00:00', '2026-11-01 11:00:00');
        $context = $this->context($owner);
        DB::enableQueryLog();
        app(TutorWorkReport::class)->summary($context);
        $first = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 0; $index < 25; $index++) {
            $this->placed($owner, '2026-11-01 10:00:00', '2026-11-01 11:00:00');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TutorWorkReport::class)->summary($context);
        $this->assertSame($first, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_source_export_keeps_entry_references_when_interval_ids_differ_and_unplaced_has_no_interval(): void
    {
        $owner = $this->owner();
        $unplaced = TutorWorkEntry::factory()->create(['created_by' => $owner->id, 'work_date' => '2026-11-01', 'description' => 'Never export this']);
        $placed = $this->placed($owner, '2026-11-01 10:00:00', '2026-11-01 11:00:00');
        $rows = iterator_to_array(app(TutorWorkExport::class)->rows($this->context($owner), 'records'));
        $this->assertSame('work:'.$placed->id, $rows[0][0]);
        $this->assertSame('work:'.$unplaced->id, $rows[1][0]);
        $this->assertSame('unplaced', $rows[1][2]);
        $this->assertSame(3600, $rows[1][11]);
        $this->assertNull($rows[1][7]);
    }
}
