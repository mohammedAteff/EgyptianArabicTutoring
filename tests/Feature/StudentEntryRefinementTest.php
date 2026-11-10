<?php

namespace Tests\Feature;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Students\Services\StudentTwoFactorService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StudentEntryRefinementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00 UTC');
        Http::preventStrayRequests();
        $this->withHeader('X-Analytics-Synthetic', '1');
        URL::forceRootUrl('http://arabicwabdallah.test');
    }

    #[TestWith([['name', 'email']])]
    #[TestWith([['name', 'phone']])]
    #[TestWith([['email', 'phone']])]
    #[TestWith([['name', 'email', 'phone']])]
    public function test_every_identity_pair_and_all_three_still_authenticate_without_javascript(array $fields): void
    {
        $student = $this->student();
        $details = ['name' => 'Amina Learner', 'email' => 'amina.entry@example.test', 'phone' => '+12025550142'];
        $this->post(route('student.login.submit'), ['date_of_birth' => '1990-01-01'] + array_intersect_key($details, array_flip($fields)))->assertRedirect(route('student.home'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    #[TestWith(['name', 'Another Person'])]
    #[TestWith(['email', 'different@example.test'])]
    #[TestWith(['phone', '+12025550143'])]
    public function test_two_correct_details_suffice_when_the_third_is_valid_but_different(string $field, string $value): void
    {
        $student = $this->student();
        $this->post(route('student.login.submit'), array_replace(['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'amina.entry@example.test', 'phone' => '+12025550142'], [$field => $value]))->assertRedirect(route('student.home'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    #[TestWith([[]])]
    #[TestWith([['name' => 'Amina Learner', 'email' => 'amina.entry@example.test']])]
    #[TestWith([['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner']])]
    #[TestWith([['date_of_birth' => 'not-a-date', 'name' => 'Amina Learner', 'email' => 'amina.entry@example.test']])]
    #[TestWith([['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'not-an-email', 'phone' => '+12025550142']])]
    #[TestWith([['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'amina.entry@example.test', 'phone' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx']])]
    #[TestWith([['date_of_birth' => '1990-01-01', 'name' => 'Wrong Name', 'email' => 'wrong@example.test']])]
    public function test_invalid_or_insufficient_details_fail_generically_without_flashing_identity(array $details): void
    {
        $this->student();
        $this->from(route('student.login'))->post(route('student.login.submit'), $details)->assertRedirect(route('student.login'))->assertSessionHasErrors(['auth' => 'The provided student details could not be verified.'])->assertSessionMissing('_old_input');
        $this->assertGuest('student');
        $this->assertNull(session('student_id'));
    }

    #[TestWith(['name'])]
    #[TestWith(['email'])]
    public function test_oversized_optional_identity_fields_cannot_bypass_validation(string $field): void
    {
        $this->student();
        $details = ['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'amina.entry@example.test', 'phone' => '+12025550142'];
        $details[$field] = str_repeat('a', 256).($field === 'email' ? '@example.test' : '');
        $this->post(route('student.login.submit'), $details)->assertSessionHasErrors(['auth' => 'The provided student details could not be verified.'])->assertSessionMissing('_old_input');
        $this->assertGuest('student');
    }

    public function test_a_valid_sign_in_recovers_after_the_existing_cooldown_expires(): void
    {
        $student = $this->student();
        $wrong = ['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'different@example.test'];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('student.login.submit'), $wrong)->assertRedirect()->assertSessionHasErrors('auth');
        }
        $this->post(route('student.login.submit'), $wrong)->assertStatus(429);
        $this->assertGuest('student');
        $this->travel(16)->minutes();
        $this->post(route('student.login.submit'), ['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'amina.entry@example.test'])->assertRedirect(route('student.home'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_sign_in_has_explicit_layout_mode_normal_post_and_associated_identity_group(): void
    {
        $response = $this->get(route('student.login'))->assertOk()->assertSee('student-portal--sign-in')->assertDontSee('student-utility-header');
        $html = $response->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(1, $xpath->query('//form[@method="POST"][@data-student-sign-in]/input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//fieldset[@aria-describedby="identity-instructions"]/legend')->length);
        $this->assertSame(1, $xpath->query('//input[@type="date"][@required][not(@value)]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="name" or @name="email" or @name="phone"][@required or @value]')->length);
        $this->capture('sign-in', $html);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_server_error_summary_preserves_the_actual_failure_without_identity_inference(bool $expired): void
    {
        if ($expired) {
            $this->get(route('student.two-factor.challenge'))->assertRedirect(route('student.login'));
            $message = 'The sign-in challenge expired. Sign in again.';
        } else {
            $this->post(route('student.login.submit'), [])->assertRedirect();
            $message = 'The provided student details could not be verified.';
        }
        $this->withCookie(config('session.cookie'), session()->getId());
        $response = $this->get(route('student.login'))->assertOk();
        $this->assertTrue(str_contains($response->getContent(), 'Please review your sign-in'));
        $this->assertTrue(str_contains($response->getContent(), $message));
        $this->assertFalse(str_contains($response->getContent(), "We Couldn't Match Those Details"));
        $this->capture($expired ? 'sign-in-expired' : 'sign-in-error', $response->getContent());
    }

    public function test_challenge_uses_one_alternative_and_retains_focused_entry_header(): void
    {
        $student = $this->student();
        $student->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now(), 'two_factor_version' => 'entry-fixture'])->save();
        $this->withSession(['student.two_factor_pending' => ['id' => $student->id, 'fingerprint' => app(StudentTwoFactorService::class)->fingerprint($student), 'expires_at' => now('UTC')->addMinutes(10)->timestamp]]);
        $response = $this->get(route('student.two-factor.challenge'))->assertOk()->assertSee('student-entry-brand')->assertSee('Use one alternative only.')->assertDontSee('student-portal--sign-in');
        $this->capture('two-factor', $response->getContent());
        $this->post(route('student.two-factor.verify'), ['code' => '123456', 'recovery_code' => 'synthetic-unused'])->assertSessionHasErrors(['code', 'recovery_code']);
        $this->assertGuest('student');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->capture('two-factor-error', $this->get(route('student.two-factor.challenge'))->assertOk()->getContent());
    }

    #[DataProvider('malformedIdentityValues')]
    public function test_malformed_identity_shapes_fail_generically_without_authentication_or_retained_input(string $field, mixed $value): void
    {
        $this->student();
        $details = ['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'amina.entry@example.test', 'phone' => '+12025550142', 'phone_country' => 'US'];
        $details[$field] = $value;
        $this->from(route('student.login'))->post(route('student.login.submit'), $details)
            ->assertStatus(302)
            ->assertRedirect(route('student.login'))
            ->assertSessionHasErrors(['auth' => 'The provided student details could not be verified.'])
            ->assertSessionMissing(['_old_input', 'student_id', 'student_authenticated_at', 'student_auth_expires_at', 'student.two_factor_verified', 'student.two_factor_pending']);
        $this->assertGuest('student');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('student.login'))->assertOk()
            ->assertSeeText('The provided student details could not be verified.')
            ->assertDontSee('amina.entry@example.test')
            ->assertDontSee('+12025550142');
    }

    /** @return array<string, array{string, mixed}> */
    public static function malformedIdentityValues(): array
    {
        $cases = [];
        foreach (['date_of_birth', 'name', 'email', 'phone', 'phone_country'] as $field) {
            foreach (['array' => ['unexpected'], 'empty-array' => [], 'nested-array' => ['nested' => ['unexpected']], 'true' => true, 'false' => false, 'number' => 123] as $shape => $value) {
                $cases[$field.'-'.$shape] = [$field, $value];
            }
        }

        return $cases;
    }

    public function test_malformed_phone_retains_identity_cooldown_route_limits_and_recovery(): void
    {
        $student = $this->student();
        $details = ['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'amina.entry@example.test', 'phone' => ['unexpected']];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from(route('student.login'))->post(route('student.login.submit'), $details)
                ->assertStatus(302)->assertRedirect(route('student.login'))
                ->assertSessionHasErrors(['auth' => 'The provided student details could not be verified.'])
                ->assertSessionMissing('_old_input');
        }
        $fingerprints = array_values(app(StudentIdentityService::class)->authFingerprints('amina.entry@example.test', null, '1990-01-01'));
        $this->assertSame(2, DB::table('student_auth_attempts')->whereIn('fingerprint', $fingerprints)->where('consecutive_failures', 3)->whereNotNull('cooldown_until')->count());
        $this->post(route('student.login.submit'), $details)->assertStatus(429)
            ->assertSessionHasErrors(['auth' => 'The provided student details could not be verified.'])->assertHeaderMissing('Retry-After');
        $this->post(route('student.login.submit'), $details)->assertStatus(429)->assertHeaderMissing('Retry-After');
        $this->post(route('student.login.submit'), $details)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest('student');
        $this->assertNull(session('student_id'));
        $this->assertNull(session('_old_input'));
        $this->travel(16)->minutes();
        $details['phone'] = '+12025550142';
        $this->post(route('student.login.submit'), $details)->assertRedirect(route('student.home'));
        $this->assertAuthenticatedAs($student, 'student');
        $this->assertSame(0, DB::table('student_auth_attempts')->whereIn('fingerprint', $fingerprints)->where('consecutive_failures', '>', 0)->count());
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['not-parseable'])]
    public function test_optional_scalar_phone_remains_a_nonmatch_when_two_other_details_match(?string $phone): void
    {
        $student = $this->student();
        $this->post(route('student.login.submit'), ['date_of_birth' => '1990-01-01', 'name' => 'Amina Learner', 'email' => 'amina.entry@example.test', 'phone' => $phone])->assertRedirect(route('student.home'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    #[TestWith(['same-origin', null, true])]
    #[TestWith([null, 'valid', true])]
    #[TestWith(['cross-site', 'valid', true])]
    #[TestWith([null, null, false])]
    #[TestWith(['cross-site', null, false])]
    #[TestWith([null, 'invalid', false])]
    public function test_installed_request_forgery_origin_and_token_fallback_without_test_bypass(?string $origin, ?string $token, bool $accepted): void
    {
        $middleware = new class(app(), app('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };
        $request = Request::create('https://arabicwabdallah.test/student/login', 'POST', $token === null ? [] : ['_token' => $token === 'valid' ? 'synthetic-csrf-token' : 'incorrect']);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('_token', 'synthetic-csrf-token');
        if ($origin !== null) {
            $request->headers->set('Sec-Fetch-Site', $origin);
        }
        if (! $accepted) {
            $this->expectException(TokenMismatchException::class);
        }
        $response = $middleware->handle($request, fn () => response('accepted'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_enabled_security_controls_preserve_the_alternative_factor_and_actions(): void
    {
        $student = $this->student();
        $student->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now(), 'two_factor_version' => 'entry-fixture'])->save();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String(), 'student.two_factor_verified' => app(StudentTwoFactorService::class)->fingerprint($student)]);
        $response = $this->get(route('student.security.show'))->assertOk()->assertSee('Replace recovery codes')->assertSee('Disable authenticator')->assertSee('Use one alternative only.')->assertDontSee('Manual key:')->assertDontSee('Save these recovery codes privately.');
        $this->capture('security-enabled', $response->getContent());
    }

    private function student(): Student
    {
        return Student::factory()->verified()->create(['first_name' => 'Amina', 'last_name' => 'Learner', 'name_normalized' => 'amina learner', 'email' => 'amina.entry@example.test', 'email_normalized' => 'amina.entry@example.test', 'phone' => '+12025550142', 'phone_normalized' => '+12025550142']);
    }

    private function capture(string $name, string $html): void
    {
        $path = getenv('STUDENT_ENTRY_EVIDENCE_PATH');
        if (! is_string($path) || $path === '') {
            return;
        }
        $base = realpath(storage_path(str_contains($path, 'student-portal-phase03-closure') ? 'app/private/student-portal-phase03-closure' : 'app/private/student-portal-phase03-evidence'));
        $resolved = realpath($path);
        if (! $base || ! $resolved || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Evidence must stay in the private Phase 03 run.');
        }
        $html = preg_replace('/(data-csrf=")[^"]*(")/i', '$1REDACTED$2', $html);
        $redacted = preg_replace('/(<(?:input|meta)\b[^>]*\bname="(?:_token|csrf-token)"[^>]*?\b(?:value|content)=")[^"]*(")/i', '$1REDACTED$2', $html);
        file_put_contents($resolved.'/'.$name.'.html', $redacted);
    }
}
