<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentPackageEntitlement;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\PackagePauseService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VaultRegressionRepairTest extends TestCase
{
    use RefreshDatabase;

    private function package(Student $student, int $credits = 3, string $type = 'one_hour', array $attributes = []): StudentPackage
    {
        $package = StudentPackage::factory()->create($attributes + [
            'student_id' => $student->id, 'identity_state' => 'mapped', 'status' => 'active',
            'total_sessions_allocated' => $credits, 'expires_at' => now('UTC')->addDays(60), 'validity_days' => 60,
            'funding_activated_at' => now('UTC'),
        ]);
        $allocation = StudentPackageEntitlement::create([
            'student_id' => $student->id, 'student_package_id' => $package->id,
            'entitlement_type_id' => EntitlementType::query()->where('code', $type)->value('id'), 'granted_quantity' => $credits,
        ]);
        if ($credits > 0) {
            SessionLedgerEntry::create([
                'student_id' => $student->id, 'student_package_id' => $package->id,
                'student_package_entitlement_id' => $allocation->id, 'entitlement_type_id' => $allocation->entitlement_type_id,
                'entry_type' => 'package_grant', 'credit_change' => $credits, 'description' => 'Original grant',
                'idempotency_key' => Str::uuid()->toString(), 'created_at' => now('UTC'),
            ]);
        }

        return $package;
    }

    private function transferData(StudentPackage $source, StudentPackage $target, string $key = 'transfer-repair'): array
    {
        return ['target_package_id' => $target->id, 'credits' => 3, 'description' => 'Move unused credits',
            'idempotency_key' => $key, 'source_allocation_id' => $source->entitlements()->sole()->id,
            'target_allocation_id' => $target->entitlements()->sole()->id];
    }

    public function test_profile_uses_actual_duration_separate_currencies_and_typed_available_credits(): void
    {
        $student = Student::factory()->verified()->create();
        $usd = $this->package($student, 3);
        $eur = $this->package($student, 4, 'two_hour', ['currency' => 'EUR']);
        $this->package($student, 9, attributes: ['paused_at' => now('UTC')]);
        $payment = PaymentRecord::factory()->create(['student_package_id' => $usd->id, 'amount_paid' => '24.37']);
        PaymentRefund::factory()->create(['payment_record_id' => $payment->id, 'amount_refunded' => '4.12']);
        PaymentRecord::factory()->create(['student_package_id' => $eur->id, 'amount_paid' => '11.08', 'currency' => 'EUR']);
        Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed', 'start_at_utc' => now('UTC')->subDays(2), 'end_at_utc' => now('UTC')->subDays(2)->addMinutes(90)]);
        $near = Booking::factory()->create(['student_id' => $student->id, 'start_at_utc' => now('UTC')->addDays(2)]);
        Booking::factory()->create(['student_id' => $student->id, 'start_at_utc' => now('UTC')->addDays(10)]);
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.students.show', $student))
            ->assertOk()->assertViewHas('lifetimeSummary', function (array $summary) use ($near): bool {
                $this->assertSame(1, $summary['completedLessonsCount']);
                $this->assertEquals(1.5, $summary['totalHours']);
                $this->assertSame(3, $summary['credits']['one_hour']['available']);
                $this->assertSame(4, $summary['credits']['two_hour']['available']);
                $this->assertSame(['paid' => '24.37', 'refunded' => '4.12'], $summary['money']['USD']);
                $this->assertSame(['paid' => '11.08', 'refunded' => '0.00'], $summary['money']['EUR']);
                $this->assertSame($near->id, $summary['nextBooking']->id);

                return true;
            })->assertSee('EUR 11.08')->assertSee('USD 24.37');
    }

    public function test_assistant_profile_has_no_financial_summary(): void
    {
        $student = Student::factory()->verified()->create();
        $this->package($student);
        $this->actingAs(Administrator::factory()->create(['role' => 'assistant']), 'web')
            ->get(route('admin.students.show', $student))->assertOk()->assertViewHas('lifetimeSummary', null)->assertDontSee('Lifetime Paid');
    }

    public function test_populated_timeline_uses_real_billing_note_and_booking_event_fields(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->package($student);
        $payment = PaymentRecord::factory()->create(['student_package_id' => $package->id, 'amount_paid' => '42.37', 'payment_method' => 'Bank transfer', 'paid_at' => now('UTC')->subDay()]);
        $refund = PaymentRefund::factory()->create(['payment_record_id' => $payment->id, 'amount_refunded' => '4.12', 'reason' => 'Duplicate receipt']);
        $note = StudentBin::factory()->create(['student_id' => $student->id, 'body' => 'Practice verb endings']);
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        $event = $booking->events()->create(['event_type' => 'rescheduled', 'performed_by' => 'admin', 'created_at' => now('UTC')]);
        $other = StudentBin::factory()->create(['body' => 'Private other student note']);
        $timeline = app(StudentTimelineService::class)->timeline(Administrator::factory()->create(), $student)->keyBy('key');
        $this->assertSame('Payment Recorded (USD 42.37)', $timeline['payment:'.$payment->id]['label']);
        $this->assertStringContainsString('Bank transfer', $timeline['payment:'.$payment->id]['detail']);
        $this->assertTrue($timeline['payment:'.$payment->id]['at']->equalTo($payment->paid_at));
        $this->assertSame('Payment Refund (USD 4.12)', $timeline['refund:'.$refund->id]['label']);
        $this->assertStringContainsString('Duplicate receipt', $timeline['refund:'.$refund->id]['detail']);
        $this->assertStringContainsString('Practice verb endings', $timeline['note:'.$note->id]['detail']);
        $this->assertArrayHasKey('booking-event:'.$event->id, $timeline->all());
        $this->assertStringContainsString('(+3)', $timeline->firstWhere('label', 'Financial: Package Grant (+3)')['label']);
        $this->assertArrayNotHasKey('note:'.$other->id, $timeline->all());
    }

    public function test_draining_transfer_replays_without_duplicate_credits_or_audit(): void
    {
        $student = Student::factory()->verified()->create();
        $source = $this->package($student);
        $target = $this->package($student, 0);
        $data = $this->transferData($source, $target);
        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']), 'web');
        $this->get(route('admin.packages.lifecycle', $source))->assertOk()
            ->assertSee('name="source_allocation_id"', false)->assertSee('name="target_allocation_id"', false)
            ->assertSee('value="'.$data['source_allocation_id'].'"', false)->assertSee('value="'.$data['target_allocation_id'].'"', false);
        $this->post(route('admin.packages.transfer', $source), $data)->assertSessionHasNoErrors();
        $target->update(['paused_at' => now('UTC')]);
        $this->post(route('admin.packages.transfer', $source), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, SessionLedgerEntry::query()->whereIn('entry_type', ['transfer_out', 'transfer_in'])->count());
        $this->assertSame(0, (int) SessionLedgerEntry::query()->where('student_package_id', $source->id)->sum('credit_change'));
        $this->assertSame(3, (int) SessionLedgerEntry::query()->where('student_package_id', $target->id)->sum('credit_change'));
        $this->assertSame(1, AuditLog::query()->where('action', 'credits_transferred')->count());
        $this->postJson(route('admin.packages.transfer', $source), array_replace($data, ['credits' => 1]))->assertUnprocessable()->assertJsonValidationErrors('credits');
        $this->assertSame(2, SessionLedgerEntry::query()->whereIn('entry_type', ['transfer_out', 'transfer_in'])->count());
    }

    public function test_transfer_rejects_other_students_mismatched_types_and_allocations_without_writes(): void
    {
        $student = Student::factory()->verified()->create();
        $source = $this->package($student);
        $other = $this->package(Student::factory()->verified()->create());
        $different = $this->package($student, 0, 'two_hour');
        $same = $this->package($student, 0);
        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']), 'web');
        foreach ([$other, $different] as $target) {
            $this->postJson(route('admin.packages.transfer', $source), $this->transferData($source, $target))->assertUnprocessable()->assertJsonValidationErrors('credits');
        }
        $this->postJson(route('admin.packages.transfer', $source), array_replace($this->transferData($source, $same), ['source_allocation_id' => $other->entitlements()->sole()->id]))->assertUnprocessable();
        $this->assertSame(0, SessionLedgerEntry::query()->whereIn('entry_type', ['transfer_out', 'transfer_in'])->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'credits_transferred')->count());
    }

    public function test_transfer_rejects_ineligible_packages_and_insufficient_balance(): void
    {
        $student = Student::factory()->verified()->create();
        $source = $this->package($student, 2);
        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']), 'web');
        foreach ([['paused_at' => now('UTC')], ['expires_at' => now('UTC')->subSecond()], ['status' => 'cancelled'], []] as $attributes) {
            $target = $this->package($student, 0, attributes: $attributes);
            $this->postJson(route('admin.packages.transfer', $source), $this->transferData($source, $target, Str::uuid()->toString()))->assertUnprocessable();
        }
        $this->assertSame(0, SessionLedgerEntry::query()->whereIn('entry_type', ['transfer_out', 'transfer_in'])->count());
    }

    public function test_only_super_admin_can_transfer_and_input_limits_match_storage(): void
    {
        $student = Student::factory()->verified()->create();
        $source = $this->package($student);
        $target = $this->package($student, 0);
        $this->actingAs(Administrator::factory()->create(['role' => 'admin']), 'web');
        $this->get(route('admin.packages.lifecycle', $source))->assertOk()->assertDontSee('Transfer Credits to Another Package');
        $this->postJson(route('admin.packages.transfer', $source), $this->transferData($source, $target))->assertForbidden();
        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']), 'web')
            ->postJson(route('admin.packages.transfer', $source), array_replace($this->transferData($source, $target), ['idempotency_key' => str_repeat('x', 65), 'description' => str_repeat('x', 201), 'source_allocation_id' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key', 'description', 'source_allocation_id']);
    }

    public function test_pause_blocks_funding_and_resume_adds_exact_elapsed_time_once(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $student = Student::factory()->verified()->create();
        $package = $this->package($student);
        $expiry = $package->expires_at;
        $actor = Administrator::factory()->create();
        $service = app(PackagePauseService::class);
        $service->pause($package, 'Travel', $actor->id);
        $this->assertFalse(app(EntitlementService::class)->canFund($student->id, $this->oneHourPackageLesson()));
        $this->assertSame('Paused', app(StudentLedgerService::class)->summary($package->fresh())['entitlement_status']);
        DB::transaction(function () use ($student): void {
            $this->assertNull(app(EntitlementService::class)->selectAndLock($student->id, $this->oneHourPackageLesson()));
        });
        $this->travel(30)->minutes();
        $service->resume($package, $actor->id);
        $this->assertTrue($package->fresh()->expires_at->equalTo($expiry->addMinutes(30)));
        $this->assertTrue(app(EntitlementService::class)->canFund($student->id, $this->oneHourPackageLesson()));
        $this->actingAs($actor, 'web')->postJson(route('admin.packages.resume', $package))->assertUnprocessable();
        $this->assertTrue($package->fresh()->expires_at->equalTo($expiry->addMinutes(30)));
        $this->assertSame(1, AuditLog::query()->where('action', 'package_resumed')->count());
    }

    public function test_immediate_resume_does_not_grant_a_free_day(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $package = $this->package(Student::factory()->verified()->create());
        $expiry = $package->expires_at;
        $actor = Administrator::factory()->create();
        app(PackagePauseService::class)->pause($package, 'Brief pause', $actor->id);
        app(PackagePauseService::class)->resume($package, $actor->id);
        $this->assertTrue($package->fresh()->expires_at->equalTo($expiry));
    }

    public function test_resume_preserves_unlimited_terms_and_extends_legacy_date_by_actual_time(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $student = Student::factory()->verified()->create();
        $actor = Administrator::factory()->create();
        $unlimited = $this->package($student, attributes: ['expires_at' => null, 'expiration_date' => null, 'validity_days' => null]);
        $legacy = $this->package($student, attributes: ['expires_at' => null, 'expiration_date' => now('UTC')->addDays(10)->toDateString()]);
        $deadline = $legacy->expiryDeadline()->startOfSecond();
        foreach ([$unlimited, $legacy] as $package) {
            app(PackagePauseService::class)->pause($package, 'Travel', $actor->id);
        }
        $this->travel(30)->minutes();
        foreach ([$unlimited, $legacy] as $package) {
            app(PackagePauseService::class)->resume($package, $actor->id);
        }
        $this->assertNull($unlimited->fresh()->expiryDeadline());
        $this->assertTrue($legacy->fresh()->expires_at->equalTo($deadline->addMinutes(30)));
    }

    public function test_populated_preview_is_read_only_and_preserves_another_students_session(): void
    {
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'UTC']);
        $other = Student::factory()->verified()->create();
        $this->package($student, 1);
        Booking::factory()->create(['student_id' => $student->id]);
        $past = Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed', 'start_at_utc' => now('UTC')->subDays(2), 'end_at_utc' => now('UTC')->subDay()]);
        LessonMaterial::factory()->create(['booking_id' => $past->id, 'student_visible' => true, 'title' => 'Shared practice material']);
        $actor = Administrator::factory()->create();
        $form = app(FormBuilderService::class)->create(['title' => 'Preview questionnaire', 'slug' => 'preview-questionnaire', 'trigger' => 'none'], [
            ['question_key' => 'goal', 'label' => 'Learning goal', 'question_type' => 'short_text'],
        ], $actor);
        app(FormBuilderService::class)->publish($form->id, $form->active_version_id, $form->lock_version);
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'title' => 'Shared homework']);
        $before = StudentNotification::query()->get()->toArray();
        $this->actingAs($other, 'student')->actingAs($actor, 'web')->withSession([
            'student_id' => $other->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'student_display_timezone' => 'Asia/Tokyo',
        ])->get(route('admin.students.preview-portal', $student))->assertOk()->assertSee('Shared practice material')
            ->assertSee('Preview questionnaire')->assertViewHas('homework', fn ($items) => $items->count() === 1)->assertViewHas('timezone', 'UTC')->assertSessionHas('student_id', $other->id)->assertSessionHas('student_display_timezone', 'Asia/Tokyo')
            ->assertDontSee('<form', false)->assertDontSee('location.reload()', false)->assertDontSee('href="'.url('/student'), false);
        $this->assertAuthenticatedAs($other, 'student');
        $this->assertSame($before, StudentNotification::query()->get()->toArray());
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])
            ->get(route('student.dashboard'))->assertOk()->assertSee('Save private feedback')->assertSee(route('student.bookings.show', $past), false);
        $this->assertGreaterThan(0, StudentNotification::query()->where('student_id', $student->id)->count());
    }
}
