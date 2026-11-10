<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\TutorInsightsContext;
use App\Domains\Reporting\Services\TutorInsightsReadModel;
use App\Domains\Reporting\Services\TutorWorkContext;
use App\Domains\Reporting\Services\TutorWorkExport;
use App\Domains\Reporting\Services\TutorWorkReport;
use App\Domains\Reporting\Services\TutorWorkService;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TutorWorkSharedWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): Administrator
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00', 'UTC'));

        return Administrator::factory()->create();
    }

    private function send(Administrator $actor, string $action, ?int $id = null, array $extra = []): TestResponse
    {
        $this->actingAs($actor->fresh(), 'web');
        $payload = $extra + ['action' => $action, 'operation_id' => (string) Str::uuid(), 'version' => 1, 'actor_context' => $actor->id, 'workspace_context' => app(TutorWorkWorkspace::class)->ready()->id, 'context_version' => TutorWorkWorkspace::PROTOCOL, 'category' => 'lesson_preparation', 'timezone' => 'UTC'];

        return $this->postJson($id ? route('admin.tutor-work.mutate', $id) : route('admin.tutor-work.store'), $payload);
    }

    public function test_workspace_is_provisioned_without_accounts_and_arbitrary_new_super_admins_share_it_without_mapping(): void
    {
        $this->assertDatabaseCount('administrators', 0);
        $this->assertDatabaseHas('tutor_work_workspaces', ['key' => 'sole-tutor', 'transition_state' => 'ready']);
        DB::table('tutor_work_workspaces')->where('key', 'sole-tutor')->update(['id' => 8877]);
        $this->getJson(route('admin.tutor-work.state'))->assertUnauthorized();
        config(['tutor_work.tutor_administrator_id' => 999999]);
        $first = Administrator::factory()->create(['id' => 10123]);
        $second = Administrator::factory()->create(['id' => 20456]);
        $workspace = (int) app(TutorWorkWorkspace::class)->ready()->id;
        foreach ([$first, $second] as $actor) {
            $this->actingAs($actor, 'web')->getJson(route('admin.tutor-work.state'))->assertJsonPath('workspace_id', $workspace)->assertJsonPath('actor_id', $actor->id)->assertJsonPath('timer', null);
        }
        $this->assertDatabaseCount('tutor_work_workspaces', 1);
        $this->assertDatabaseCount('tutor_work_entries', 0);
        $this->assertDatabaseCount('tutor_work_revisions', 0);
    }

    public function test_two_actors_control_one_timer_with_distinct_creation_segment_and_revision_provenance(): void
    {
        $a = $this->actor();
        $b = Administrator::factory()->create();
        $id = $this->send($a, 'start')->assertOk()->json('result.entry_id');
        $this->send($b, 'start')->assertConflict()->assertJsonPath('current.timer.id', $id);
        $this->travel(30)->seconds();
        $this->send($b, 'pause', $id)->assertOk();
        $this->travel(2)->minutes();
        $this->actingAs($a, 'web')->getJson(route('admin.tutor-work.state'))->assertJsonPath('timer.elapsed_seconds', 30)->assertJsonPath('timer.lifecycle', 'paused');
        $this->send($b, 'resume', $id, ['version' => 2])->assertOk();
        $this->travel(45)->seconds();
        $this->send($b, 'stop', $id, ['version' => 3])->assertJsonPath('result.acceptance', 'accepted');
        $entry = TutorWorkEntry::findOrFail($id);
        $this->assertSame($a->id, $entry->created_by);
        $this->assertNull($entry->administrator_id);
        $this->assertSame(75, app(TutorWorkService::class)->seconds($entry));
        $this->assertSame([$a->id, $b->id], DB::table('tutor_work_segments')->where('entry_id', $id)->orderBy('id')->pluck('opened_by')->all());
        $this->assertSame([$b->id, $b->id], DB::table('tutor_work_segments')->where('entry_id', $id)->orderBy('id')->pluck('closed_by')->all());
        $this->assertSame([$a->id, $b->id, $b->id, $b->id], DB::table('tutor_work_revisions')->where('entry_id', $id)->orderBy('id')->pluck('actor_id')->all());
        $this->assertDatabaseCount('tutor_work_entries', 1);
    }

    public function test_cross_actor_manual_correction_confirmation_and_exclusion_keep_one_canonical_record(): void
    {
        $a = $this->actor();
        $b = Administrator::factory()->create();
        $manual = ['placement' => 'unplaced', 'work_date' => '2026-11-03', 'hours' => 13, 'minutes' => 0, 'description' => '<script>private</script>'];
        $id = $this->send($a, 'manual', extra: $manual)->assertJsonPath('result.acceptance', 'needs_review')->json('result.entry_id');
        $this->send($b, 'confirm', $id)->assertJsonPath('result.acceptance', 'accepted');
        $this->send($b, 'correct', $id, ['version' => 2, 'hours' => 1, 'reason' => 'Private synthetic timing correction'] + $manual)->assertOk();
        $this->send($a, 'exclude', $id, ['version' => 3, 'reason' => 'Private synthetic exclusion'])->assertOk();
        $this->assertDatabaseCount('tutor_work_entries', 1);
        $this->assertDatabaseHas('tutor_work_entries', ['id' => $id, 'created_by' => $a->id, 'version' => 4, 'acceptance' => 'excluded', 'unplaced_seconds' => 3600]);
        $this->assertSame([$a->id, $b->id, $b->id, $a->id], DB::table('tutor_work_revisions')->where('entry_id', $id)->orderBy('id')->pluck('actor_id')->all());
        $this->actingAs($b, 'web')->get(route('admin.tutor-work.show', $id))->assertSee('&lt;script&gt;private&lt;/script&gt;', false)->assertDontSee('<script>private</script>', false);
    }

    #[TestWith(['admin'])]
    #[TestWith(['assistant'])]
    public function test_non_super_roles_are_denied_every_work_boundary_without_private_bootstrap(string $role): void
    {
        $creator = $this->actor();
        $id = $this->send($creator, 'start')->json('result.entry_id');
        $actor = Administrator::factory()->create(['role' => $role]);
        $this->actingAs($actor, 'web');
        foreach (['index', 'state', 'export'] as $route) {
            $this->getJson(route('admin.tutor-work.'.$route))->assertForbidden();
        }
        $this->getJson(route('admin.tutor-work.show', $id))->assertForbidden();
        $this->postJson(route('admin.tutor-work.store'), [])->assertForbidden();
        $this->postJson(route('admin.tutor-work.mutate', $id), [])->assertForbidden();
        if ($role === 'admin') {
            $this->get(route('admin.tutor-insights.index'))->assertOk()->assertDontSee('data-work-shell', false)->assertDontSee('Timer &amp; manual entry', false);
            $this->get(route('admin.tutor-insights.index', ['view' => 'operations']))->assertOk();
        }
    }

    public function test_stale_database_role_and_account_or_protocol_context_cannot_mutate(): void
    {
        $a = $this->actor();
        $b = Administrator::factory()->create();
        $this->send($a, 'start', extra: ['actor_context' => $b->id])->assertConflict();
        $this->send($a, 'start', extra: ['context_version' => 'account-private-v1'])->assertConflict();
        $this->send($a, 'start', extra: ['workspace_context' => 999999])->assertConflict();
        DB::table('administrators')->where('id', $a->id)->update(['role' => 'admin']);
        $this->actingAs($a, 'web')->getJson(route('admin.tutor-work.state'))->assertForbidden();
        $this->assertDatabaseCount('tutor_work_entries', 0);
    }

    public function test_same_uuid_is_actor_scoped_and_old_start_retry_cannot_start_a_replacement(): void
    {
        $a = $this->actor();
        $b = Administrator::factory()->create();
        $uuid = (string) Str::uuid();
        $first = $this->send($a, 'start', extra: ['operation_id' => $uuid])->json('result.entry_id');
        $this->send($b, 'start', extra: ['operation_id' => $uuid])->assertConflict();
        $this->send($b, 'stop', $first)->assertOk();
        $second = $this->send($b, 'start', extra: ['operation_id' => $uuid])->assertOk()->json('result.entry_id');
        $this->assertNotSame($first, $second);
        $this->send($a, 'start', extra: ['operation_id' => $uuid])->assertJsonPath('replayed', true)->assertJsonPath('result.entry_id', $first)->assertJsonPath('current.timer.id', $second);
        $this->send($a, 'start', extra: ['operation_id' => $uuid, 'category' => 'feedback'])->assertConflict();
        $this->assertDatabaseCount('tutor_work_entries', 2);
        $this->assertDatabaseCount('tutor_work_operations', 3);
    }

    #[TestWith(['creator'])]
    #[TestWith(['controller'])]
    #[TestWith(['unrelated'])]
    #[TestWith(['paused'])]
    public function test_revocation_affects_only_the_current_running_segment_opener(string $case): void
    {
        $a = $this->actor();
        $b = Administrator::factory()->create();
        $unrelated = Administrator::factory()->create();
        $id = $this->send($a, 'start')->json('result.entry_id');
        $this->travel(1)->minute();
        $this->send($b, 'pause', $id)->assertOk();
        if ($case !== 'paused') {
            $this->send($b, 'resume', $id, ['version' => 2])->assertOk();
            $this->travel(2)->minutes();
        }
        $subject = match ($case) {
            'controller', 'paused' => $b, 'creator' => $a, default => $unrelated
        };
        $subject->update(['suspended_at' => now()]);
        $this->actingAs($subject->fresh(), 'web')->getJson(route('admin.tutor-work.state'))->assertRedirect(route('admin.login'));
        $reader = $case === 'creator' ? $b : $a;
        $this->actingAs($reader->fresh(), 'web');
        if ($case === 'controller') {
            $this->getJson(route('admin.tutor-work.state'))->assertJsonPath('timer', null);
            $this->assertDatabaseHas('tutor_work_entries', ['id' => $id, 'lifecycle' => 'stopped', 'acceptance' => 'needs_review']);
            $this->assertSame(180, app(TutorWorkService::class)->seconds(TutorWorkEntry::findOrFail($id)));
            $this->assertDatabaseHas('tutor_work_revisions', ['entry_id' => $id, 'actor_id' => null, 'subject_administrator_id' => $b->id, 'system_action' => true]);
        } else {
            $this->getJson(route('admin.tutor-work.state'))->assertJsonPath('timer.lifecycle', $case === 'paused' ? 'paused' : 'running');
            $this->assertDatabaseCount('tutor_work_revisions', $case === 'paused' ? 2 : 3);
        }
    }

    public function test_account_deletion_retains_workspace_evidence_and_another_actor_can_review(): void
    {
        $a = $this->actor();
        $b = Administrator::factory()->create();
        $id = $this->send($a, 'start')->json('result.entry_id');
        $this->travel(1)->minute();
        $a->delete();
        $this->assertDatabaseCount('tutor_work_entries', 1);
        $this->assertDatabaseCount('tutor_work_segments', 1);
        $this->send($b, 'confirm', $id, ['version' => 2])->assertJsonPath('result.acceptance', 'accepted');
        $a->forceDelete();
        $this->assertDatabaseHas('tutor_work_entries', ['id' => $id, 'created_by' => null, 'acceptance' => 'accepted']);
        $this->actingAs($b, 'web')->get(route('admin.tutor-work.show', $id))->assertSee('Former administrator');
    }

    public function test_teaching_is_counted_once_and_cross_month_workload_is_clipped_for_both_actors_and_exports(): void
    {
        $a = $this->actor();
        $b = Administrator::factory()->create();
        Booking::factory()->create(['student_id' => null, 'status' => 'completed', 'start_at_utc' => '2026-10-31 23:30:00', 'end_at_utc' => '2026-11-01 00:30:00']);
        foreach ([['2026-10-01', '2026-10-31', 1, 3600], ['2026-11-01', '2026-11-03', 0, 0]] as [$start, $end, $appointments, $seconds]) {
            $filters = ['range' => 'custom', 'start_date' => $start, 'end_date' => $end, 'timezone' => 'UTC'];
            $teaching = app(TutorInsightsReadModel::class)->metrics(TutorInsightsContext::fromRequest(request()->duplicate($filters), null));
            $this->assertSame($appointments, $teaching['appointments_completed']['value']);
            $this->assertSame($seconds, $teaching['recorded_teaching_seconds']['value']);
            foreach ([$a, $b] as $actor) {
                $context = TutorWorkContext::fromRequest(request()->duplicate($filters), $actor->id);
                $summary = app(TutorWorkReport::class)->summary($context);
                $this->assertSame(1800, $summary['union']);
                $this->assertSame(1800, $summary['gross']);
                $rows = iterator_to_array(app(TutorWorkExport::class)->rows($context, 'allocations'));
                $this->assertCount(1, $rows);
                $this->assertSame(1800, $rows[0][11]);
                $this->assertSame('sole-tutor', $context->metadata()['workspace_key']);
                $this->actingAs($actor, 'web');
                $csv = $this->get(route('admin.tutor-work.export', $filters + ['dataset' => 'allocations', 'format' => 'csv']))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->streamedContent();
                $csvLines = preg_split('/\r\n|\n|\r/', trim($csv));
                $this->assertCount(2, $csvLines);
                $this->assertSame('1800', str_getcsv($csvLines[1])[11]);
                $response = $this->get(route('admin.tutor-work.export', $filters + ['dataset' => 'allocations', 'format' => 'xlsx']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
                $path = $response->baseResponse->getFile()->getPathname();
                try {
                    $sheet = IOFactory::load($path)->getActiveSheet();
                    $this->assertSame(1800, $sheet->getCell('L2')->getValue());
                    $this->assertSame('n', $sheet->getCell('L2')->getDataType());
                    $this->assertSame(2, $sheet->getHighestRow());
                } finally {
                    unlink($path);
                }
            }
        }
    }
}
