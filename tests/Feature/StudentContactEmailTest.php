<?php

namespace Tests\Feature;

use App\Domains\Resources\Services\EmailQualityService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentEmail;
use App\Domains\Students\Services\StudentEmailService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class StudentContactEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_addition_is_immediate_normalized_and_does_not_send_mail_or_enable_sign_in(): void
    {
        Mail::fake();
        $this->routableEmail();
        $student = Student::factory()->verified()->create();
        $this->studentSession($student);

        $this->post(route('student.profile.email.request'), ['email' => 'SECONDARY@routable.example'])
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'The email address has been added to your student profile.');

        $this->assertDatabaseHas('student_emails', ['student_id' => $student->id, 'email_normalized' => 'secondary@routable.example', 'verified_at' => null]);
        $this->assertDatabaseCount('student_email_verifications', 0);
        $this->assertFalse(app(StudentEmailService::class)->trusted($student, 'secondary@routable.example'));
        $this->assertSame(0, $student->verifiedEmails()->count());
        $this->get(route('student.profile'))->assertOk()->assertSee('Additional contact address')->assertSee('Add Email Address')->assertDontSee('Send verification');
        Mail::assertNothingOutgoing();

        $this->post(route('student.logout'));
        $this->post(route('student.login.submit'), ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => 'secondary@routable.example'])
            ->assertSessionHasErrors('auth');
        $this->assertGuest('student');
    }

    public function test_existing_verified_alias_and_timestamp_remain_eligible_for_sign_in(): void
    {
        Mail::fake();
        $this->routableEmail();
        $this->freezeTime();
        $student = Student::factory()->verified()->create();
        $verified = StudentEmail::factory()->create(['student_id' => $student->id, 'email_normalized' => 'trusted@routable.example', 'verified_at' => now('UTC')->subDays(12)]);
        $timestamp = $verified->verified_at->toIso8601String();
        $this->studentSession($student);

        $this->post(route('student.profile.email.request'), ['email' => 'contact@routable.example'])->assertSessionHasNoErrors();
        $this->assertSame($timestamp, $verified->fresh()->verified_at->toIso8601String());
        $this->assertTrue(app(StudentEmailService::class)->trusted($student, 'trusted@routable.example'));
        Mail::assertNothingOutgoing();

        $this->post(route('student.logout'));
        $this->post(route('student.login.submit'), ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => 'trusted@routable.example'])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_primary_contact_trusted_and_cross_profile_collisions_are_rejected(): void
    {
        Mail::fake();
        $this->routableEmail();
        $student = Student::factory()->verified()->create(['email' => 'primary@routable.example', 'email_normalized' => 'primary@routable.example']);
        $other = Student::factory()->verified()->create(['email' => 'other@routable.example', 'email_normalized' => 'other@routable.example']);
        StudentEmail::factory()->create(['student_id' => $other->id, 'email_normalized' => 'other-contact@routable.example', 'verified_at' => null]);
        StudentEmail::factory()->create(['student_id' => $other->id, 'email_normalized' => 'other-trusted@routable.example']);
        $this->studentSession($student);

        $this->post(route('student.profile.email.request'), ['email' => 'own-contact@routable.example'])->assertSessionHasNoErrors();
        foreach (['primary@routable.example', 'OWN-CONTACT@routable.example', 'other@routable.example', 'other-contact@routable.example', 'other-trusted@routable.example'] as $email) {
            $this->post(route('student.profile.email.request'), ['email' => $email])->assertSessionHasErrors('email');
        }
        $this->assertSame(1, StudentEmail::query()->where('student_id', $student->id)->count());
        $this->assertDatabaseCount('student_email_verifications', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_addition_requires_authentication_and_ignores_a_forged_owner(): void
    {
        Mail::fake();
        $this->routableEmail();
        $this->post(route('student.profile.email.request'), ['email' => 'contact@routable.example'])->assertRedirect(route('student.login'));
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $this->studentSession($student);
        $this->post(route('student.profile.email.request'), ['email' => 'contact@routable.example', 'student_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_emails', ['student_id' => $student->id, 'email_normalized' => 'contact@routable.example', 'verified_at' => null]);
        $this->assertSame(0, StudentEmail::query()->where('student_id', $other->id)->count());
        Mail::assertNothingOutgoing();
    }

    public function test_invalid_inputs_create_no_address_and_send_no_mail(): void
    {
        Mail::fake();
        $student = Student::factory()->verified()->create();
        $this->studentSession($student);
        $this->post(route('student.profile.email.request'), [])->assertSessionHasErrors('email');
        $this->post(route('student.profile.email.request'), ['email' => 'not-an-address'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('student_emails', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_rollback_refuses_to_discard_or_trust_contact_only_addresses(): void
    {
        $student = Student::factory()->verified()->create();
        $contact = StudentEmail::factory()->create(['student_id' => $student->id, 'verified_at' => null]);
        $migration = require database_path('migrations/2026_10_09_030037_allow_contact_only_student_emails.php');
        try {
            $migration->down();
            $this->fail('Rollback must refuse contact-only data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('must be preserved', $exception->getMessage());
        }
        $this->assertDatabaseHas('student_emails', ['id' => $contact->id, 'verified_at' => null]);
    }

    public function test_merge_moves_both_address_types_without_promoting_contact_trust(): void
    {
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $contact = StudentEmail::factory()->create(['student_id' => $secondary->id, 'verified_at' => null]);
        $verified = StudentEmail::factory()->create(['student_id' => $secondary->id]);
        $timestamp = $verified->verified_at->toIso8601String();

        app(StudentMergeService::class)->merge($primary->id, $secondary->id);

        $this->assertDatabaseHas('student_emails', ['id' => $contact->id, 'student_id' => $primary->id, 'verified_at' => null]);
        $this->assertSame($timestamp, $verified->fresh()->verified_at->toIso8601String());
        $this->assertFalse(app(StudentEmailService::class)->trusted($primary, $contact->email_normalized));
        $this->assertTrue(app(StudentEmailService::class)->trusted($primary, $verified->email_normalized));
    }

    public function test_existing_privacy_erasure_removes_both_owned_address_types(): void
    {
        $student = Student::factory()->verified()->create();
        StudentEmail::factory()->create(['student_id' => $student->id, 'verified_at' => null]);
        StudentEmail::factory()->create(['student_id' => $student->id]);
        app(StudentPrivacyService::class)->anonymize($student->id);
        $this->assertSame(0, StudentEmail::query()->where('student_id', $student->id)->count());
    }

    public function test_a_primary_email_changed_before_the_lock_cannot_be_added_again_as_a_contact(): void
    {
        Mail::fake();
        $this->routableEmail();
        $student = Student::factory()->verified()->create();
        Student::query()->whereKey($student->id)->update(['email' => 'current-primary@routable.example', 'email_normalized' => 'current-primary@routable.example']);

        try {
            app(StudentEmailService::class)->addContactAddress($student, 'current-primary@routable.example');
            $this->fail('The current locked primary address must not be added as a second address.');
        } catch (ValidationException $exception) {
            $this->assertSame('This email address is already connected to your student profile.', $exception->errors()['email'][0]);
        }
        $this->assertDatabaseCount('student_emails', 0);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'email_normalized' => 'current-primary@routable.example']);
        Mail::assertNothingOutgoing();
    }

    private function routableEmail(): void
    {
        $this->partialMock(EmailQualityService::class)->shouldReceive('dnsRecords')->andReturn([['type' => 'MX', 'target' => 'mail.routable.example']]);
    }

    private function studentSession(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }
}
