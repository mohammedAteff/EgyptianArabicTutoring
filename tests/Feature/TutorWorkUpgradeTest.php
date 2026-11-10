<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\TutorWorkInput;
use App\Domains\Reporting\Services\TutorWorkService;
use App\Domains\Reporting\Services\TutorWorkTransition;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

/** Schema-changing fixtures must not retain Eloquent column metadata across schema versions. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class TutorWorkUpgradeTest extends TestCase
{
    private const UPGRADE = 'database/migrations/2026_11_04_130940_add_shared_workspace_to_tutor_work.php';

    /** The inherited TestCase guard runs before this fixture can reset the dedicated test database. */
    private function legacySchema(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00', 'UTC'));
        RefreshDatabaseState::$migrated = false;
        $paths = [];
        foreach (glob(database_path('migrations/*.php')) as $path) {
            $relative = 'database/migrations/'.basename($path);
            if (strcmp($relative, self::UPGRADE) < 0) {
                $paths[] = $relative;
            }
        }
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--path' => $paths, '--force' => true, '--no-interaction' => true]));
    }

    private function upgrade(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--path' => [self::UPGRADE], '--no-interaction' => true]));
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function legacyEntry(Administrator $owner, string $state = 'stopped', ?string $uuid = null, int $hours = 1): array
    {
        $uuid ??= (string) Str::uuid();
        $action = $state === 'stopped' ? 'manual' : 'start';
        $payload = ['action' => $action, 'operation_id' => $uuid, 'version' => 1, 'category' => 'feedback', 'timezone' => 'UTC', 'description' => 'PRIVATE legacy description', 'placement' => 'unplaced', 'work_date' => '2026-11-03', 'hours' => $hours, 'minutes' => 0];
        $declaration = app(TutorWorkInput::class)->normalize($payload, $action);
        $entry = TutorWorkEntry::factory()->legacy()->make(['administrator_id' => $owner->id, 'origin' => $action === 'start' ? 'timer' : 'manual', 'placement' => $action === 'start' ? 'placed' : 'unplaced', 'active_owner' => $action === 'start' ? $owner->id : null, 'lifecycle' => $state, 'acceptance' => $state === 'stopped' ? ($hours >= 12 ? 'needs_review' : 'accepted') : 'provisional', 'category' => $declaration['category'], 'work_date' => $declaration['work_date'] ?? now('UTC')->toDateString(), 'unplaced_seconds' => $state === 'stopped' ? $hours * 3600 : null, 'description' => 'PRIVATE legacy description', 'original_declaration' => $declaration]);
        unset($entry->workspace_id, $entry->created_by);
        $entry->save();
        DB::table('tutor_work_revisions')->insert(['entry_id' => $entry->id, 'administrator_id' => $owner->id, 'from_version' => 0, 'to_version' => 1, 'action' => $action, 'reason' => 'PRIVATE legacy reason', 'before_value' => '{}', 'after_value' => json_encode(['administrator_id' => $owner->id, 'original' => 'PRIVATE legacy snapshot'], JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
        $result = ['entry_id' => $entry->id, 'version' => 1, 'lifecycle' => $state, 'acceptance' => $entry->acceptance];
        DB::table('tutor_work_operations')->insert(['administrator_id' => $owner->id, 'operation_id' => $uuid, 'fingerprint' => hash('sha256', json_encode([$action, null, $declaration], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
        if ($state !== 'stopped') {
            DB::table('tutor_work_segments')->insert(['entry_id' => $entry->id, 'started_at' => '2026-11-04 11:00:00', 'ended_at' => '2026-11-04 11:10:00']);
            if ($state === 'running') {
                DB::table('tutor_work_segments')->insert(['entry_id' => $entry->id, 'started_at' => '2026-11-04 11:30:00', 'open_entry' => $entry->id]);
            }
        }

        return [$entry, $payload];
    }

    public function test_actual_empty_phase04_upgrade_initializes_without_a_super_admin_or_mapping(): void
    {
        $this->legacySchema();
        $this->assertDatabaseCount('administrators', 0);
        $before = app(TutorWorkTransition::class)->preflight();
        $this->assertFalse($before['schema']['phase04a_applied']);
        $this->assertSame([], $before['legacy_entry_ids']);
        $this->upgrade();
        $this->assertSame('ready', app(TutorWorkWorkspace::class)->find()->transition_state);
        $this->assertDatabaseCount('administrators', 0);
        $this->getJson(route('admin.tutor-work.state'))->assertUnauthorized();
        $actor = Administrator::factory()->create(['id' => 9043]);
        $this->actingAs($actor, 'web')->getJson(route('admin.tutor-work.state'))->assertOk()->assertJsonPath('timer', null);
        $this->assertDatabaseCount('tutor_work_workspaces', 1);
    }

    public function test_ready_phase04a_without_phase05_schema_keeps_portal_reports_and_exports_available(): void
    {
        $this->legacySchema();
        $this->upgrade();
        $this->assertFalse(Schema::hasTable('estimated_activity_intervals'));
        $this->assertSame('ready', app(TutorWorkWorkspace::class)->find()->transition_state);
        $this->assertSame('off', config('estimated_activity.mode'));
        $actor = Administrator::factory()->create();
        $this->actingAs($actor, 'web');
        foreach (['admin.student-insights.index', 'admin.tutor-insights.index', 'admin.tutor-work.index', 'admin.feedback.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('data-estimated-activity');
        }
        foreach (['overview', 'history', 'controls'] as $view) {
            $this->get(route('admin.tutor-work.index', ['view' => $view]))->assertOk();
        }
        foreach (['admin.student-insights.export', 'admin.tutor-insights.export', 'admin.tutor-work.export'] as $route) {
            $this->get(route($route, ['format' => 'csv']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $response = $this->get(route($route, ['format' => 'xlsx']))->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            try {
                $this->assertGreaterThan(0, IOFactory::load($path)->getActiveSheet()->getHighestRow());
            } finally {
                unlink($path);
            }
        }
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
        foreach (['student.insights.index', 'student.feedback.index', 'student.learning.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('data-estimated-activity');
        }
        $this->assertFalse(Schema::hasTable('estimated_activity_intervals'));
    }

    public function test_multiple_legacy_ledgers_require_a_complete_explicit_decision_and_preserve_snapshots_receipts_and_private_scopes(): void
    {
        $this->legacySchema();
        $a = Administrator::factory()->create();
        $b = Administrator::factory()->create(['role' => 'admin']);
        $uuid = (string) Str::uuid();
        [$adopted, $payload] = $this->legacyEntry($a, uuid: $uuid);
        [$preserved] = $this->legacyEntry($b, uuid: $uuid);
        $oldSnapshot = DB::table('tutor_work_revisions')->where('entry_id', $adopted->id)->value('after_value');
        $oldResult = DB::table('tutor_work_operations')->where('administrator_id', $a->id)->value('result');
        $this->upgrade();
        $transition = app(TutorWorkTransition::class);
        $preflight = $transition->preflight();
        $this->assertSame('pending', $preflight['workspace_state']);
        $this->assertCount(2, $preflight['legacy_scopes']);
        $this->assertSame(1, $preflight['operation_uuids_in_multiple_actor_scopes']);
        $this->assertStringNotContainsString('PRIVATE legacy', json_encode($preflight, JSON_THROW_ON_ERROR));
        $this->actingAs($a, 'web')->get(route('admin.tutor-work.index'))->assertSee('Legacy transition decision pending');
        $this->postJson(route('admin.tutor-work.store'), $payload)->assertStatus(423);
        try {
            $transition->convert([$adopted->id], [], false, $preflight['digest']);
            $this->fail('Incomplete legacy decision was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('every legacy entry', $exception->getMessage());
        }
        $converted = $transition->convert([$adopted->id], [$preserved->id], false, $preflight['digest']);
        $this->assertSame('applied', $converted['result']);
        $this->assertSame(hash('sha256', json_encode($preflight['preservation'], JSON_THROW_ON_ERROR)), $converted['preservation_digest']);
        $this->assertSame('already_applied', $transition->convert([$adopted->id], [$preserved->id], false, $preflight['digest'])['result']);
        $this->assertSame($oldSnapshot, DB::table('tutor_work_revisions')->where('entry_id', $adopted->id)->value('after_value'));
        $this->assertSame($oldResult, DB::table('tutor_work_operations')->where('administrator_id', $a->id)->value('result'));
        $this->assertDatabaseHas('tutor_work_revisions', ['entry_id' => $adopted->id, 'administrator_id' => $a->id, 'actor_id' => $a->id]);
        $this->assertDatabaseHas('tutor_work_entries', ['id' => $preserved->id, 'workspace_id' => null, 'administrator_id' => $b->id, 'acceptance' => 'accepted']);
        $replay = app(TutorWorkService::class)->mutate($a->id, 'manual', null, $payload);
        $this->assertTrue($replay['legacy_receipt']);
        $this->assertSame($adopted->id, $replay['result']['entry_id']);
        $this->assertDatabaseCount('tutor_work_entries', 2);
        $this->assertDatabaseCount('tutor_work_operations', 2);
        $c = Administrator::factory()->create();
        $this->actingAs($c, 'web')->get(route('admin.tutor-work.show', $adopted->id))->assertOk();
        $this->get(route('admin.tutor-work.show', $preserved->id))->assertNotFound();
        $b->update(['role' => 'super_admin']);
        $this->actingAs($b->fresh(), 'web')->get(route('admin.tutor-work.show', $preserved->id))->assertNotFound();
        $this->postJson(route('admin.tutor-work.store'), $payload + ['actor_context' => $b->id, 'workspace_context' => app(TutorWorkWorkspace::class)->ready()->id, 'context_version' => TutorWorkWorkspace::PROTOCOL])->assertConflict()->assertSee('not repeated');
        $this->assertDatabaseCount('tutor_work_operations', 2);
        $this->assertDatabaseCount('tutor_work_entries', 2);
    }

    #[TestWith(['running', 1])]
    #[TestWith(['running', 2])]
    #[TestWith(['paused', 1])]
    public function test_active_legacy_timers_require_explicit_single_timestamp_cutover_and_never_resume_as_shared(string $state, int $count): void
    {
        $this->legacySchema();
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            [$entry] = $this->legacyEntry(Administrator::factory()->create(), $state);
            $ids[] = $entry->id;
        }
        $this->upgrade();
        $transition = app(TutorWorkTransition::class);
        $preflight = $transition->preflight();
        try {
            $transition->convert($ids, [], false, $preflight['digest']);
            $this->fail('Active legacy timers converted without an explicit cutover.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('--cutover-active', $exception->getMessage());
        }
        $this->assertSame($count, TutorWorkEntry::where('lifecycle', $state)->count());
        $result = $transition->convert($ids, [], true, $preflight['digest']);
        $this->assertSame($ids, $result['finalized_legacy_timers']);
        foreach (TutorWorkEntry::whereIn('id', $ids)->get() as $entry) {
            $this->assertSame('stopped', $entry->lifecycle);
            $this->assertSame('needs_review', $entry->acceptance);
            $this->assertNull($entry->active_workspace);
            $this->assertNull($entry->active_owner);
            $this->assertSame($state === 'running' ? 2400 : 600, app(TutorWorkService::class)->seconds($entry));
            $this->assertDatabaseHas('tutor_work_revisions', ['entry_id' => $entry->id, 'action' => 'legacy_cutover', 'actor_id' => null, 'system_action' => true]);
        }
        $this->assertSame(0, DB::table('tutor_work_segments')->whereNotNull('open_entry')->count());
        $this->assertSame($count, DB::table('tutor_work_revisions')->where('action', 'legacy_cutover')->where('created_at', '2026-11-04 12:00:00')->count());
        $this->assertDatabaseCount('tutor_work_operations', $count);
        $this->assertSame('already_applied', $transition->convert($ids, [], true, $preflight['digest'])['result']);
    }

    public function test_changed_preflight_and_preservation_failure_abort_without_partial_cutover_and_down_is_honest(): void
    {
        $this->legacySchema();
        [$entry] = $this->legacyEntry(Administrator::factory()->create(), 'running');
        $this->upgrade();
        $transition = app(TutorWorkTransition::class);
        $before = $transition->preflight();
        DB::table('tutor_work_revisions')->where('entry_id', $entry->id)->update(['reason' => 'Updated private evidence']);
        try {
            $transition->convert([$entry->id], [], true, $before['digest']);
            $this->fail('Stale preflight converted changed evidence.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('evidence changed', $exception->getMessage());
        }
        $other = Administrator::factory()->create();
        DB::table('tutor_work_operations')->where('administrator_id', $entry->administrator_id)->update(['administrator_id' => $other->id]);
        $mismatched = $transition->preflight();
        $this->assertSame(1, $mismatched['receipt_scope_mismatches']);
        try {
            $transition->convert([$entry->id], [], true, $mismatched['digest']);
            $this->fail('An inconsistent legacy receipt scope was adopted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ownership mismatches', $exception->getMessage());
        }
        DB::table('tutor_work_operations')->where('administrator_id', $other->id)->update(['administrator_id' => $entry->administrator_id]);
        $current = $transition->preflight();
        TutorWorkEntry::updating(function (TutorWorkEntry $record): void {
            $record->description = 'Unexpected alteration';
        });
        try {
            $transition->convert([$entry->id], [], true, $current['digest']);
            $this->fail('Preservation mismatch committed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('preservation check failed', $exception->getMessage());
        } finally {
            Event::forget('eloquent.updating: '.TutorWorkEntry::class);
        }
        $this->assertSame('pending', app(TutorWorkWorkspace::class)->find()->transition_state);
        $this->assertDatabaseHas('tutor_work_entries', ['id' => $entry->id, 'lifecycle' => 'running', 'description' => 'PRIVATE legacy description', 'version' => 1]);
        $this->assertDatabaseHas('tutor_work_segments', ['open_entry' => $entry->id, 'ended_at' => null]);
        $this->assertDatabaseCount('tutor_work_revisions', 1);
        $migration = require base_path(self::UPGRADE);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('forward-only');
        $migration->down();
    }

    public function test_stopped_accepted_excluded_and_review_states_and_equal_unplaced_declarations_survive_adoption(): void
    {
        $this->legacySchema();
        $owner = Administrator::factory()->create();
        [$accepted] = $this->legacyEntry($owner);
        [$excluded] = $this->legacyEntry($owner);
        [$review] = $this->legacyEntry($owner, hours: 13);
        $before = app(TutorWorkService::class)->snapshot($excluded);
        $excluded->update(['acceptance' => 'excluded', 'version' => 2]);
        DB::table('tutor_work_revisions')->insert(['entry_id' => $excluded->id, 'administrator_id' => $owner->id, 'from_version' => 1, 'to_version' => 2, 'action' => 'exclude', 'reason' => 'Private legacy exclusion', 'before_value' => json_encode($before, JSON_THROW_ON_ERROR), 'after_value' => json_encode(app(TutorWorkService::class)->snapshot($excluded), JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
        $ids = [$accepted->id, $excluded->id, $review->id];
        $originals = DB::table('tutor_work_entries')->orderBy('id')->pluck('original_declaration', 'id')->all();
        $this->upgrade();
        $transition = app(TutorWorkTransition::class);
        $preflight = $transition->preflight();
        $result = $transition->convert($ids, [], false, $preflight['digest']);
        $this->assertSame('applied', $result['result']);
        $this->assertSame(['accepted', 'excluded', 'needs_review'], DB::table('tutor_work_entries')->orderBy('id')->pluck('acceptance')->all());
        $this->assertSame([3600, 3600, 46800], DB::table('tutor_work_entries')->orderBy('id')->pluck('unplaced_seconds')->all());
        $this->assertSame($originals, DB::table('tutor_work_entries')->orderBy('id')->pluck('original_declaration', 'id')->all());
        $this->assertDatabaseCount('tutor_work_entries', 3);
        $this->assertDatabaseCount('tutor_work_revisions', 4);
        $this->assertDatabaseCount('tutor_work_operations', 3);
        $this->assertFalse($transition->preflight()['decision_required']);
    }

    public function test_partial_workspace_schema_blocks_work_without_breaking_legacy_security_revocation(): void
    {
        $this->legacySchema();
        $owner = Administrator::factory()->create();
        [$entry] = $this->legacyEntry($owner, 'running');
        Schema::create('tutor_work_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('transition_state');
        });
        DB::table('tutor_work_workspaces')->insert(['key' => 'sole-tutor', 'transition_state' => 'schema_pending']);
        $owner->update(['password' => 'PrivateSyntheticSecurityChange123!']);
        $this->assertDatabaseHas('tutor_work_entries', ['id' => $entry->id, 'lifecycle' => 'stopped', 'acceptance' => 'needs_review', 'active_owner' => null]);
        $this->assertSame(2400, app(TutorWorkService::class)->seconds($entry->fresh()));
        $this->actingAs($owner->fresh(), 'web')->get(route('admin.tutor-work.index'))->assertSee('Database migration pending');
        $preflight = app(TutorWorkTransition::class)->preflight();
        $this->assertFalse($preflight['schema']['phase04a_applied']);
        $this->assertFalse($preflight['schema']['phase04a_migration_recorded']);
        $this->assertDatabaseCount('tutor_work_revisions', 2);
    }
}
