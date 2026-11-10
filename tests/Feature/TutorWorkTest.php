<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\TutorWorkService;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use App\Domains\Students\Models\Student;
use App\Policies\TutorWorkEntryPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TutorWorkTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): Administrator
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00.900', 'UTC'));
        $owner = Administrator::factory()->create(['role' => 'super_admin']);
        $this->actingAs($owner, 'web');

        return $owner;
    }

    private function payload(string $action = 'start', array $extra = []): array
    {
        return $extra + ['actor_context' => (string) auth('web')->id(), 'workspace_context' => (string) app(TutorWorkWorkspace::class)->ready()->id, 'context_version' => TutorWorkWorkspace::PROTOCOL, 'action' => $action, 'operation_id' => (string) Str::uuid(), 'version' => 1, 'category' => 'lesson_preparation', 'timezone' => 'UTC', 'description' => 'Private synthetic declaration'];
    }

    private function command(int $id, string $action, int $version): TestResponse
    {
        return $this->postJson(route('admin.tutor-work.mutate', $id), $this->payload($action, ['version' => $version]));
    }

    public function test_timer_saves_multiple_segments_excludes_pause_and_recovers_without_state_read_writes(): void
    {
        $owner = $this->owner();
        $start = $this->postJson(route('admin.tutor-work.store'), $this->payload())->assertOk();
        $id = $start->json('result.entry_id');
        $this->travel(20)->minutes();
        $this->command($id, 'pause', 1)->assertJsonPath('current.timer.elapsed_seconds', 1200);
        $this->travel(10)->minutes();
        $this->getJson(route('admin.tutor-work.state'))->assertJsonPath('timer.lifecycle', 'paused')->assertJsonPath('timer.elapsed_seconds', 1200);
        $this->command($id, 'resume', 2)->assertJsonPath('result.version', 3);
        $this->travel(30)->minutes();
        $this->command($id, 'stop', 3)->assertJsonPath('result.acceptance', 'accepted')->assertJsonPath('current.timer', null);
        $entry = TutorWorkEntry::findOrFail($id);
        $this->assertSame(3000, app(TutorWorkService::class)->seconds($entry));
        $this->assertDatabaseCount('tutor_work_segments', 2);
        $this->assertDatabaseCount('tutor_work_intervals', 2);
        $counts = [DB::table('tutor_work_operations')->count(), DB::table('tutor_work_revisions')->count(), $entry->updated_at->toDateTimeString()];
        $this->get(route('admin.tutor-insights.index'))->assertOk();
        $this->getJson(route('admin.tutor-work.state'))->assertOk();
        $this->assertSame($counts, [DB::table('tutor_work_operations')->count(), DB::table('tutor_work_revisions')->count(), $entry->fresh()->updated_at->toDateTimeString()]);
        $this->assertSame($owner->id, $entry->created_by);
        $this->assertNull($entry->active_workspace);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_stopping_paused_or_zero_timer_releases_account_exclusivity(bool $positive): void
    {
        $this->owner();
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        if ($positive) {
            $this->travel(1)->minute();
        }
        $this->command($id, 'pause', 1)->assertOk();
        $this->travel(1)->hour();
        $this->command($id, 'stop', 2)->assertJsonPath('result.acceptance', $positive ? 'accepted' : 'excluded');
        $this->assertSame($positive ? 60 : 0, app(TutorWorkService::class)->seconds(TutorWorkEntry::findOrFail($id)));
        $this->postJson(route('admin.tutor-work.store'), $this->payload())->assertOk();
        $this->command($id, 'resume', 3)->assertConflict();
    }

    public function test_retry_identity_returns_original_result_and_cannot_replace_a_new_timer(): void
    {
        $this->owner();
        $payload = $this->payload();
        $id = $this->postJson(route('admin.tutor-work.store'), $payload)->json('result.entry_id');
        $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonPath('replayed', true)->assertJsonPath('result.entry_id', $id);
        $this->postJson(route('admin.tutor-work.store'), array_replace($payload, ['category' => 'other']))->assertConflict();
        $this->postJson(route('admin.tutor-work.store'), $this->payload())->assertConflict()->assertJsonPath('current.timer.id', $id);
        $this->travel(2)->minutes();
        $stop = $this->payload('stop');
        $this->postJson(route('admin.tutor-work.mutate', $id), $stop)->assertOk();
        $this->postJson(route('admin.tutor-work.mutate', $id), $stop)->assertJsonPath('replayed', true);
        $new = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonPath('result.entry_id', $id)->assertJsonPath('current.timer.id', $new);
        $this->assertDatabaseCount('tutor_work_entries', 2);
        $this->assertDatabaseCount('tutor_work_operations', 3);
    }

    public function test_logout_and_reauthentication_do_not_pause_the_timer(): void
    {
        $owner = $this->owner();
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        $this->post(route('admin.logout'))->assertRedirect();
        $this->travel(2)->hours();
        $this->actingAs($owner->fresh(), 'web');
        $this->getJson(route('admin.tutor-work.state'))->assertJsonPath('timer.elapsed_seconds', 7200)->assertJsonPath('timer.id', $id)->assertJsonPath('timer.lifecycle', 'running');
        $this->assertDatabaseCount('tutor_work_segments', 1);
    }

    #[TestWith(['elapsed_seconds', 123])]
    #[TestWith(['administrator_id', 999])]
    #[TestWith(['student_id', 1])]
    #[TestWith(['duration_seconds', 600])]
    #[TestWith(['operation_id', 'weak'])]
    public function test_rejects_untrusted_authority_duration_and_request_identity(string $key, mixed $value): void
    {
        $this->owner();
        $this->postJson(route('admin.tutor-work.store'), $this->payload(extra: [$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
        $this->assertDatabaseCount('tutor_work_entries', 0);
    }

    public function test_manual_unplaced_history_and_corrections_preserve_private_original_evidence(): void
    {
        $this->owner();
        $payload = $this->payload('manual', ['placement' => 'unplaced', 'work_date' => '2020-01-02', 'hours' => 2, 'minutes' => 0, 'description' => "  مواد <script>bad</script>\nSecond paragraph  "]);
        $id = $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonPath('result.acceptance', 'accepted')->json('result.entry_id');
        $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonPath('replayed', true);
        $this->postJson(route('admin.tutor-work.store'), array_replace($payload, ['operation_id' => (string) Str::uuid()]))->assertOk();
        $entry = TutorWorkEntry::findOrFail($id);
        $original = $entry->original_declaration;
        $this->assertSame(7200, $entry->unplaced_seconds);
        $this->assertDatabaseCount('tutor_work_intervals', 0);
        $this->get(route('admin.tutor-work.show', $id))->assertSee('&lt;script&gt;bad&lt;/script&gt;', false)->assertDontSee('<script>bad</script>', false);
        $correction = array_replace($payload, ['action' => 'correct', 'operation_id' => (string) Str::uuid(), 'hours' => 1]);
        $this->postJson(route('admin.tutor-work.mutate', $id), $correction)->assertJsonValidationErrors('reason');
        $correction['reason'] = 'Private correction reason';
        $this->postJson(route('admin.tutor-work.mutate', $id), $correction)->assertJsonPath('result.version', 2);
        $this->postJson(route('admin.tutor-work.mutate', $id), $this->payload('exclude', ['version' => 1, 'reason' => 'Stale']))->assertConflict();
        $this->postJson(route('admin.tutor-work.mutate', $id), $this->payload('exclude', ['version' => 2, 'reason' => 'Duplicate declaration']))->assertJsonPath('result.acceptance', 'excluded');
        $this->assertSame($original, $entry->fresh()->original_declaration);
        $this->assertSame(3600, $entry->fresh()->unplaced_seconds);
        $this->assertDatabaseCount('tutor_work_revisions', 4);
    }

    #[TestWith(['2026-11-05', 'UTC', 2, 0])]
    #[TestWith(['2026-02-30', 'UTC', 2, 0])]
    #[TestWith(['2026-03-29', 'Europe/Berlin', 24, 0])]
    #[TestWith(['2026-11-01', 'UTC', 0, 0])]
    public function test_unplaced_rejects_future_invalid_zero_and_overlong_local_date(string $date, string $zone, int $hours, int $minutes): void
    {
        $this->owner();
        $this->postJson(route('admin.tutor-work.store'), $this->payload('manual', ['placement' => 'unplaced', 'work_date' => $date, 'timezone' => $zone, 'hours' => $hours, 'minutes' => $minutes]))->assertUnprocessable();
        $this->assertDatabaseCount('tutor_work_entries', 0);
    }

    public function test_precise_intervals_require_valid_wall_times_and_explicit_dst_occurrence(): void
    {
        $this->owner();
        $payload = $this->payload('manual', ['placement' => 'placed', 'timezone' => 'Europe/Berlin', 'intervals' => [['start' => '2026-03-29T02:30:00', 'end' => '2026-03-29T04:00:00']]]);
        $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonValidationErrors('intervals.0.start')->assertSee('does not exist');
        $payload['intervals'] = [['start' => '2026-10-25T02:30:00', 'end' => '2026-10-25T03:30:00']];
        $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonValidationErrors('intervals.0.start')->assertSee('occurs twice');
        $payload['intervals'][0]['start_fold'] = 'first';
        $id = $this->postJson(route('admin.tutor-work.store'), $payload)->assertOk()->json('result.entry_id');
        $this->assertSame(7200, app(TutorWorkService::class)->seconds(TutorWorkEntry::findOrFail($id)));
        $payload['operation_id'] = (string) Str::uuid();
        $payload['intervals'][0]['start'] = '2026-10-25T02:30:00+00:00';
        $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonValidationErrors('intervals.0.start');
    }

    public function test_long_work_requires_review_and_timer_duration_only_correction_replaces_effective_intervals(): void
    {
        $this->owner();
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        $this->travel(13)->hours();
        $this->getJson(route('admin.tutor-work.state'))->assertJsonPath('timer.warning', true)->assertJsonPath('timer.review_due', true)->assertJsonPath('timer.lifecycle', 'running');
        $this->command($id, 'stop', 1)->assertJsonPath('result.acceptance', 'needs_review');
        $raw = DB::table('tutor_work_segments')->get()->toJson();
        $this->command($id, 'confirm', 2)->assertJsonPath('result.acceptance', 'accepted');
        $correction = $this->payload('correct', ['version' => 3, 'placement' => 'unplaced', 'work_date' => '2026-11-04', 'hours' => 2, 'minutes' => 0, 'reason' => 'Forgot to stop. Only duration is known.']);
        $this->postJson(route('admin.tutor-work.mutate', $id), $correction)->assertJsonPath('result.acceptance', 'accepted');
        $this->assertSame($raw, DB::table('tutor_work_segments')->get()->toJson());
        $this->assertDatabaseCount('tutor_work_intervals', 0);
        $this->assertSame('timer', TutorWorkEntry::findOrFail($id)->origin);
        $this->get(route('admin.tutor-work.show', $id))->assertSee('duration-only manual correction');
        $correction = array_replace($correction, ['operation_id' => (string) Str::uuid(), 'version' => 4, 'hours' => 13]);
        $this->postJson(route('admin.tutor-work.mutate', $id), $correction)->assertJsonPath('result.acceptance', 'needs_review');
    }

    #[TestWith(['suspended_at'])]
    #[TestWith(['role'])]
    #[TestWith(['password'])]
    #[TestWith(['two_factor_version'])]
    #[TestWith(['delete'])]
    public function test_security_authority_changes_stop_timer_at_trusted_transition_and_require_review(string $change): void
    {
        $owner = $this->owner();
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        $this->travel(5)->minutes();
        if ($change === 'delete') {
            $owner->delete();
        } else {
            $owner->forceFill([$change => match ($change) {
                'role' => 'assistant', 'password' => 'ReplacementPassword123!', 'suspended_at' => now('UTC'), default => (string) Str::uuid()
            }])->save();
        }
        $entry = TutorWorkEntry::findOrFail($id);
        $this->assertSame('stopped', $entry->lifecycle);
        $this->assertSame('needs_review', $entry->acceptance);
        $this->assertNull($entry->active_workspace);
        $this->assertSame(300, app(TutorWorkService::class)->seconds($entry));
        $this->assertDatabaseHas('tutor_work_revisions', ['entry_id' => $id, 'action' => 'security_revocation']);
    }

    public function test_paused_security_revocation_does_not_add_paused_time_and_recovery_does_not_resume(): void
    {
        $owner = $this->owner();
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        $this->travel(1)->minute();
        $this->command($id, 'pause', 1)->assertOk();
        $this->travel(4)->hours();
        app(AdministratorTwoFactorService::class)->emergencyRecover($owner->id, 'Synthetic operator', 'Synthetic recovery');
        $this->assertSame(0, app(TutorWorkService::class)->seconds(TutorWorkEntry::findOrFail($id)));
        $this->actingAs($owner->fresh(), 'web');
        $this->getJson(route('admin.tutor-work.state'))->assertJsonPath('timer.lifecycle', 'paused')->assertJsonPath('timer.elapsed_seconds', 60);
    }

    public function test_rolled_back_account_revocation_and_timer_mutation_leave_no_partial_evidence(): void
    {
        $owner = $this->owner();
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        DB::beginTransaction();
        $owner->update(['suspended_at' => now()]);
        DB::rollBack();
        $this->assertSame('running', TutorWorkEntry::findOrFail($id)->lifecycle);
        $this->assertDatabaseCount('tutor_work_revisions', 1);
        DB::beginTransaction();
        app(TutorWorkService::class)->mutate($owner->id, 'pause', $id, $this->payload('pause'));
        DB::rollBack();
        $this->assertSame('running', TutorWorkEntry::findOrFail($id)->lifecycle);
        $this->assertDatabaseCount('tutor_work_operations', 1);
        $this->assertDatabaseHas('tutor_work_segments', ['entry_id' => $id, 'ended_at' => null]);
    }

    #[TestWith(['admin'])]
    #[TestWith(['super_admin'])]
    public function test_shared_record_is_available_only_to_other_eligible_super_admins(string $role): void
    {
        $this->owner();
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->assertOk()->json('result.entry_id');
        $other = Administrator::factory()->create(['role' => $role]);
        $this->actingAs($other, 'web');
        if ($role === 'admin') {
            $this->get(route('admin.tutor-work.show', $id))->assertForbidden();
            $this->get(route('admin.tutor-work.show', 999999))->assertForbidden();
            $this->command($id, 'stop', 1)->assertForbidden();
            $this->getJson(route('admin.tutor-work.state'))->assertForbidden();
            $this->get(route('admin.tutor-work.export'))->assertForbidden();
        } else {
            $this->get(route('admin.tutor-work.show', $id))->assertOk();
            $this->get(route('admin.tutor-work.show', 999999))->assertNotFound();
            $this->getJson(route('admin.tutor-work.state'))->assertJsonPath('timer.id', $id);
            $this->command($id, 'stop', 1)->assertOk();
            $this->assertStringNotContainsString('Private synthetic declaration', $this->get(route('admin.tutor-work.export'))->streamedContent());
        }
    }

    public function test_guest_and_assistant_are_denied_all_private_boundaries(): void
    {
        $this->get(route('admin.tutor-work.index'))->assertRedirect(route('admin.login'));
        $assistant = Administrator::factory()->create(['role' => 'assistant']);
        $this->actingAs($assistant, 'web');
        foreach (['index', 'state', 'export'] as $route) {
            $this->getJson(route('admin.tutor-work.'.$route))->assertForbidden();
        }
        $this->postJson(route('admin.tutor-work.store'), $this->payload())->assertForbidden();
    }

    public function test_history_paginates_and_all_subviews_render_with_private_text_escaped(): void
    {
        $owner = $this->owner();
        TutorWorkEntry::factory()->count(23)->create(['created_by' => $owner->id]);
        foreach (['overview', 'history', 'controls'] as $view) {
            $response = $this->get(route('admin.tutor-work.index', ['view' => $view]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            if ($view === 'history') {
                $this->assertCount(20, $response->viewData('entries'));
                $this->assertSame(23, $response->viewData('entries')->total());
            }
        }
    }

    public function test_category_only_correction_needs_no_reason_and_preserves_confirmed_review_disposition(): void
    {
        $this->owner();
        $payload = $this->payload('manual', ['placement' => 'unplaced', 'work_date' => '2026-11-01', 'hours' => 13, 'minutes' => 0]);
        $id = $this->postJson(route('admin.tutor-work.store'), $payload)->json('result.entry_id');
        $this->command($id, 'confirm', 1)->assertOk();
        $payload = array_replace($payload, ['action' => 'correct', 'operation_id' => (string) Str::uuid(), 'version' => 2, 'category' => 'other', 'description' => 'Unicode العربية français Deutsch']);
        $this->postJson(route('admin.tutor-work.mutate', $id), $payload)->assertJsonPath('result.acceptance', 'accepted');
        $this->assertSame('other', TutorWorkEntry::findOrFail($id)->category);
    }

    public function test_manual_precise_multiday_duration_is_derived_and_effective_revision_replaces_previous_intervals(): void
    {
        $this->owner();
        $payload = $this->payload('manual', ['placement' => 'placed', 'intervals' => [['start' => '2026-11-01T23:00:00', 'end' => '2026-11-03T01:00:00']]]);
        $id = $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonPath('result.acceptance', 'needs_review')->json('result.entry_id');
        $this->assertSame(93600, app(TutorWorkService::class)->seconds(TutorWorkEntry::findOrFail($id)));
        $payload = array_replace($payload, ['action' => 'correct', 'operation_id' => (string) Str::uuid(), 'version' => 1, 'reason' => 'Two known work intervals only', 'intervals' => [['start' => '2026-11-01T23:00:00', 'end' => '2026-11-02T00:00:00'], ['start' => '2026-11-02T01:00:00', 'end' => '2026-11-02T02:00:00']]]);
        $this->postJson(route('admin.tutor-work.mutate', $id), $payload)->assertJsonPath('result.acceptance', 'accepted');
        $this->assertSame(7200, app(TutorWorkService::class)->seconds(TutorWorkEntry::findOrFail($id)));
        $this->assertDatabaseCount('tutor_work_intervals', 2);
    }

    #[TestWith(['2026-11-04T13:00:00', '2026-11-04T14:00:00'])]
    #[TestWith(['2026-11-03T10:00:00', '2026-11-03T10:00:00'])]
    #[TestWith(['2026-11-03T11:00:00', '2026-11-03T10:00:00'])]
    #[TestWith(['2026-02-30T11:00:00', '2026-03-01T10:00:00'])]
    public function test_precise_rejects_future_zero_reversed_and_invalid_dates(string $start, string $end): void
    {
        $this->owner();
        $this->postJson(route('admin.tutor-work.store'), $this->payload('manual', ['placement' => 'placed', 'intervals' => [['start' => $start, 'end' => $end]]]))->assertUnprocessable();
        $this->assertDatabaseCount('tutor_work_entries', 0);
    }

    public function test_description_reason_limits_and_conflict_current_record_are_private_and_clear(): void
    {
        $this->owner();
        $this->postJson(route('admin.tutor-work.store'), $this->payload(extra: ['description' => str_repeat('ع', 1001)]))->assertJsonValidationErrors('description');
        $payload = $this->payload('manual', ['placement' => 'unplaced', 'work_date' => '2026-11-01', 'hours' => 1, 'minutes' => 0, 'description' => str_repeat('ع', 1000)]);
        $id = $this->postJson(route('admin.tutor-work.store'), $payload)->assertOk()->json('result.entry_id');
        $this->postJson(route('admin.tutor-work.mutate', $id), $this->payload('exclude', ['reason' => str_repeat('ع', 501)]))->assertJsonValidationErrors('reason');
        $this->postJson(route('admin.tutor-work.mutate', $id), $this->payload('exclude', ['version' => 99, 'reason' => 'Stale']))->assertConflict()->assertJsonPath('record.version', 1)->assertJsonPath('record.acceptance', 'accepted');
        $this->assertDatabaseCount('tutor_work_operations', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_changed_account_context_is_denied_and_state_refresh_supports_same_account_reauthentication(): void
    {
        $owner = $this->owner();
        $this->getJson(route('admin.tutor-work.state'))->assertJsonPath('actor_id', $owner->id)->assertJsonStructure(['csrf', 'server_at', 'timer']);
        $this->postJson(route('admin.tutor-work.store'), $this->payload(extra: ['actor_context' => $owner->id + 1]))->assertConflict();
        $this->assertDatabaseCount('tutor_work_entries', 0);
    }

    public function test_shared_policy_allows_other_super_admin_and_denies_ineligible_actor(): void
    {
        $owner = $this->owner();
        $entry = TutorWorkEntry::factory()->create(['created_by' => $owner->id]);
        $policy = app(TutorWorkEntryPolicy::class);
        $this->assertTrue($policy->view($owner, $entry));
        $this->assertTrue($policy->view(Administrator::factory()->create(['role' => 'super_admin']), $entry));
        $owner->update(['role' => 'assistant']);
        $this->assertFalse($policy->view($owner, $entry));
        $owner->update(['role' => 'admin', 'suspended_at' => now()]);
        $this->assertFalse($policy->view($owner, $entry));
    }

    public function test_zero_length_security_frozen_timer_is_still_visible_for_owner_review(): void
    {
        $owner = $this->owner();
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        $owner->update(['password' => 'New synthetic password']);
        $this->actingAs($owner->fresh(), 'web');
        $this->get(route('admin.tutor-work.index', ['view' => 'history', 'state' => 'needs_review']))->assertSee('Lesson preparation · #'.$id);
        $this->command($id, 'confirm', 2)->assertConflict();
    }

    public function test_student_session_cannot_access_private_admin_work_routes(): void
    {
        $this->actingAs(Student::factory()->verified()->create(), 'student');
        $this->get(route('admin.tutor-work.index'))->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.tutor-work.state'))->assertUnauthorized();
        $this->postJson(route('admin.tutor-work.store'), $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('tutor_work_entries', 0);
    }

    public function test_state_sequence_advances_across_stop_and_replacement_and_old_receipts_return_current_sequence(): void
    {
        $this->owner();
        $payload = $this->payload();
        $first = $this->postJson(route('admin.tutor-work.store'), $payload);
        $id = $first->json('result.entry_id');
        $sequence = $first->json('current.sequence');
        $stopped = $this->command($id, 'stop', 1);
        $this->assertGreaterThan($sequence, $stopped->json('current.sequence'));
        $new = $this->postJson(route('admin.tutor-work.store'), $this->payload());
        $this->assertGreaterThan($stopped->json('current.sequence'), $new->json('current.sequence'));
        $this->postJson(route('admin.tutor-work.store'), $payload)->assertJsonPath('current.sequence', $new->json('current.sequence'))->assertJsonPath('current.timer.id', $new->json('result.entry_id'));
    }

    public function test_database_failure_rolls_back_and_does_not_expose_private_sql_values(): void
    {
        $this->owner();
        TutorWorkEntry::creating(function (TutorWorkEntry $entry): void {
            $entry->active_workspace = null;
        });
        try {
            $this->postJson(route('admin.tutor-work.store'), $this->payload(extra: ['description' => 'NEVER EXPOSE PRIVATE SQL CONTENT']))->assertServiceUnavailable()->assertDontSee('NEVER EXPOSE PRIVATE SQL CONTENT')->assertSee('could not be committed');
            $this->assertDatabaseCount('tutor_work_entries', 0);
            $this->assertDatabaseCount('tutor_work_segments', 0);
            $this->assertDatabaseCount('tutor_work_operations', 0);
        } finally {
            Event::forget('eloquent.creating: '.TutorWorkEntry::class);
        }
    }

    public function test_history_dates_exclude_a_paused_timer_when_only_its_pause_gap_crosses_the_window(): void
    {
        $this->owner();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'UTC'));
        $id = $this->postJson(route('admin.tutor-work.store'), $this->payload())->json('result.entry_id');
        $this->travel(10)->minutes();
        $this->command($id, 'pause', 1)->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00', 'UTC'));
        $filters = ['view' => 'history', 'range' => 'custom', 'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'timezone' => 'UTC', 'state' => 'provisional'];
        $this->assertCount(0, $this->get(route('admin.tutor-work.index', $filters))->assertOk()->viewData('entries'));
        $filters['start_date'] = $filters['end_date'] = '2026-11-02';
        $this->assertCount(1, $this->get(route('admin.tutor-work.index', $filters))->assertOk()->viewData('entries'));
    }
}
