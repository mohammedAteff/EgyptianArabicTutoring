<?php

namespace Tests\Feature;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentIdentityService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_student_can_sign_in_with_dob_and_two_normalized_identifiers(): void
    {
        $student = Student::factory()->verified()->create([
            'first_name' => 'Maya', 'last_name' => 'Lee', 'name_normalized' => 'maya lee',
            'email' => 'Maya+Study@Example.com', 'email_normalized' => 'maya+study@example.com',
        ]);
        $this->assertSame(1, Student::verified()->whereDate('date_of_birth', '1990-01-01')->where('email_normalized', 'maya+study@example.com')->count());

        $this->post(route('student.login.submit'), [
            'date_of_birth' => '1990-01-01', 'name' => 'MAYA LEE', 'email' => ' maya+study@example.com ',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student, 'student');
        $this->assertSame($student->id, session('student_id'));
        $this->assertTrue(now('UTC')->addMinutes(179)->lessThan(session('student_auth_expires_at')));
        $this->get(route('student.dashboard'))->assertOk()->assertSee('Welcome, Maya')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_unverified_or_ambiguous_students_cannot_authenticate(): void
    {
        $identity = [
            'first_name' => 'Maya', 'last_name' => 'Lee', 'name_normalized' => 'maya lee',
            'email' => 'maya@example.com', 'email_normalized' => 'maya@example.com',
            'date_of_birth' => '1990-01-01',
        ];
        Student::factory()->create($identity);
        $credentials = ['date_of_birth' => '1990-01-01', 'name' => 'Maya Lee', 'email' => 'maya@example.com'];

        $this->post(route('student.login.submit'), $credentials)->assertRedirect()->assertSessionHasErrors('auth');
        $this->assertGuest('student');

        Student::factory()->verified()->count(2)->create($identity);
        $this->post(route('student.login.submit'), $credentials)->assertRedirect()->assertSessionHasErrors('auth');
        $this->assertGuest('student');
        $this->assertDatabaseHas('admin_notifications', ['title' => 'Ambiguous student verification']);
    }

    public function test_login_fails_closed_when_duplicate_matches_follow_three_one_identifier_candidates(): void
    {
        $credentials = ['date_of_birth' => '1990-01-01', 'name' => 'Maya Lee', 'email' => 'maya@example.com'];
        $matchingStudent = [
            'first_name' => 'Maya', 'last_name' => 'Lee', 'name_normalized' => 'maya lee',
            'email' => 'maya@example.com', 'email_normalized' => 'maya@example.com',
        ];

        Student::factory()->verified()->create($matchingStudent);
        Student::factory()->verified()->create([
            'first_name' => 'Maya', 'last_name' => 'Other', 'name_normalized' => 'maya other',
            'email' => 'name-only@example.com', 'email_normalized' => 'name-only@example.com',
        ]);
        Student::factory()->verified()->create([
            'first_name' => 'Other', 'last_name' => 'Person', 'name_normalized' => 'other person',
            'email' => 'maya@example.com', 'email_normalized' => 'maya@example.com',
        ]);
        Student::factory()->verified()->create($matchingStudent);

        $this->post(route('student.login.submit'), $credentials)
            ->assertRedirect()
            ->assertSessionHasErrors('auth');

        $this->assertGuest('student');
        $this->assertDatabaseHas('admin_notifications', ['title' => 'Ambiguous student verification']);
    }

    public function test_three_consecutive_failures_trigger_atomic_cooldown(): void
    {
        $credentials = ['date_of_birth' => '1990-01-01', 'name' => 'Maya Lee', 'email' => 'maya@example.com'];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('student.login.submit'), $credentials)->assertRedirect()->assertSessionHasErrors('auth');
        }

        $this->post(route('student.login.submit'), $credentials)->assertStatus(429);
        $this->assertDatabaseHas('student_auth_attempts', ['consecutive_failures' => 3]);
    }

    public function test_student_verification_hmac_limit_is_shared_across_rotating_ips(): void
    {
        $student = Student::factory()->verified()->create();
        $credentials = [
            'date_of_birth' => '1990-01-01',
            'name' => 'Different Person',
            'email' => $student->email,
        ];
        $fingerprints = array_values(app(StudentIdentityService::class)->authFingerprints($student->email, null, '1990-01-01'));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$attempt])
                ->post(route('student.login.submit'), $credentials)
                ->assertRedirect()
                ->assertSessionHasErrors('auth');
            DB::table('student_auth_attempts')->whereIn('fingerprint', $fingerprints)->update([
                'consecutive_failures' => 0,
                'cooldown_until' => null,
            ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.6'])
            ->post(route('student.login.submit'), $credentials)
            ->assertStatus(429);
    }

    public function test_expiry_and_status_change_block_the_next_portal_request(): void
    {
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->withSession([
            'student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->subMinutes(181)->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->subMinute()->toIso8601String(),
        ])->get(route('student.dashboard'))->assertRedirect(route('student.login'));

        $this->actingAs($student, 'student')->withSession([
            'student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
        ]);
        Cache::forget('student_auth_check_'.$student->id);
        $student->update(['identity_status' => 'merged']);
        $this->get(route('student.dashboard'))->assertRedirect(route('student.login'));
    }

    public function test_student_logout_does_not_destroy_admin_authentication(): void
    {
        $admin = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        Auth::guard('web')->login($admin);
        Auth::guard('student')->login($student, false);

        $this->withSession([
            'student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
        ])->post(route('student.logout'))->assertRedirect(route('student.login'));

        $this->assertGuest('student');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNull(session('student_id'));
    }
}
