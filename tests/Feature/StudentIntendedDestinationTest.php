<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentTwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class StudentIntendedDestinationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00 UTC');
        Http::preventStrayRequests();
        $this->withHeader('X-Analytics-Synthetic', '1');
    }

    #[TestWith(['/student/home'])]
    #[TestWith(['/student'])]
    #[TestWith(['/student/my-learning'])]
    #[TestWith(['/student/my-learning/notes'])]
    #[TestWith(['/student/learn/for-you/1'])]
    #[TestWith(['/student/learn/courses/1'])]
    #[TestWith(['/student/learn/courses/1/lessons/2'])]
    #[TestWith(['/student/learn/courses/1/lessons/2/assessments/3'])]
    #[TestWith(['/student/learning'])]
    #[TestWith(['/student/planning'])]
    #[TestWith(['/student/notes'])]
    #[TestWith(['/student/notes/1/edit'])]
    #[TestWith(['/student/bookmarks'])]
    #[TestWith(['/student/profile'])]
    #[TestWith(['/student/security'])]
    #[TestWith(['/student/my-learning/browsers'])]
    #[TestWith(['/student/notifications'])]
    #[TestWith(['/student/statements'])]
    #[TestWith(['/student/packages/1/statement'])]
    #[TestWith(['/student/packages/1/receipts/2'])]
    #[TestWith(['/student/bookings/create'])]
    #[TestWith(['/student/bookings/1'])]
    #[TestWith(['/student/bookings/1/reschedule'])]
    #[TestWith(['/student/lessons/1'])]
    #[TestWith(['/student/forms/learning-goals'])]
    #[TestWith(['/student/progress-reports/1'])]
    public function test_unauthenticated_supported_html_requests_save_only_the_student_destination(string $path): void
    {
        $this->withSession(['url.intended' => '/admin/private']);
        $this->get($path.'?q=hello%20world&page=2&timezone=Africa%2FCairo&date=2026-11-01&session_type_id=1&next=https%3A%2F%2Fevil.test&token=secret')
            ->assertRedirect(route('student.login'))
            ->assertSessionHas('student.intended', $path.'?q=hello%20world&page=2&timezone=Africa%2FCairo&date=2026-11-01&session_type_id=1')
            ->assertSessionHas('url.intended', '/admin/private');
    }

    #[TestWith(['/student/logout'])]
    #[TestWith(['/student/my-learning/submissions/1/file'])]
    #[TestWith(['/student/packages/1/statement/export'])]
    #[TestWith(['/student/learn/courses/1/lessons/2/materials/3'])]
    #[TestWith(['/student/security/enrollment/approve'])]
    #[TestWith(['/student/profile/emails/verify/fake-token'])]
    #[TestWith(['/student/lessons/1/materials/2'])]
    #[TestWith(['/student/homework/1/link'])]
    #[TestWith(['/student/assigned-resources/1'])]
    public function test_actions_downloads_and_signed_links_do_not_replace_a_supported_destination(string $path): void
    {
        $this->withSession(['student.intended' => '/student/planning']);
        $this->get($path)->assertSessionHas('student.intended', '/student/planning');
    }

    #[TestWith(['https://evil.test/student/home'])]
    #[TestWith(['//evil.test/student/home'])]
    #[TestWith(['/student/../admin'])]
    #[TestWith(['/student/%2e%2e/admin'])]
    #[TestWith(['/student/login'])]
    #[TestWith(['/student/two-factor-challenge'])]
    #[TestWith(['/student/home%0d%0aLocation:evil'])]
    #[TestWith(['/student\\home'])]
    #[TestWith(['/student/unknown'])]
    #[TestWith([['/student/home']])]
    public function test_malformed_or_unapproved_saved_destinations_fall_back_and_are_consumed(mixed $url): void
    {
        $student = $this->student();
        $this->withSession(['student.intended' => $url, 'url.intended' => '/admin/private']);
        $this->post(route('student.login.submit'), $this->credentials($student))->assertRedirect(route('student.home'))
            ->assertSessionMissing('student.intended')->assertSessionHas('url.intended', '/admin/private');
    }

    public function test_failed_login_keeps_the_destination_then_success_restores_it_once_and_logout_clears_it(): void
    {
        $student = $this->student();
        $this->get('/student/notes?q=Arabic')->assertRedirect(route('student.login'));
        $this->post(route('student.login.submit'), ['date_of_birth' => '1990-01-01'])->assertSessionHasErrors('auth')->assertSessionHas('student.intended', '/student/notes?q=Arabic');
        $this->post(route('student.login.submit'), $this->credentials($student))->assertRedirect('/student/notes?q=Arabic')->assertSessionMissing('student.intended');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('student.login'))->assertRedirect(route('student.home'));
        $this->withSession(['student.intended' => '/student/notes'])->post(route('student.logout'))->assertRedirect(route('student.login'))->assertSessionMissing('student.intended');
        $this->post(route('student.login.submit'), $this->credentials($student))->assertRedirect(route('student.home'));
    }

    public function test_two_factor_keeps_intent_across_failed_codes_and_only_consumes_after_full_success(): void
    {
        $student = $this->student();
        $secret = 'ABCDEFGHIJKLMNOP';
        $student->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_version' => 'home-test-version'])->save();
        $this->get('/student/my-learning')->assertRedirect(route('student.login'));
        $this->post(route('student.login.submit'), $this->credentials($student))->assertRedirect(route('student.two-factor.challenge'))->assertSessionHas('student.intended', '/student/my-learning');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('student.login'))->assertOk()->assertDontSee('Student mobile navigation');
        $this->post(route('student.two-factor.verify'), ['code' => 'bad'])->assertSessionHasErrors()->assertSessionHas('student.intended', '/student/my-learning');
        $otp = app(Google2FA::class)->oathTotp($secret, intdiv(now('UTC')->timestamp, 30));
        $this->post(route('student.two-factor.verify'), ['code' => $otp])->assertRedirect('/student/my-learning')->assertSessionMissing('student.intended');
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_restoration_does_not_bypass_ownership_and_non_html_requests_never_capture_intent(): void
    {
        $student = $this->student();
        $foreign = Booking::factory()->create(['student_id' => Student::factory()->verified()->create()->id]);
        $this->getJson('/student/home')->assertSessionMissing('student.intended');
        $this->post('/student/bookings', [])->assertSessionMissing('student.intended');
        $this->get(route('student.bookings.show', $foreign))->assertRedirect(route('student.login'));
        $this->post(route('student.login.submit'), $this->credentials($student))->assertRedirect(route('student.bookings.show', $foreign));
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('student.bookings.show', $foreign))->assertNotFound();
    }

    public function test_login_redirect_requires_valid_session_context_including_two_factor_proof(): void
    {
        $student = $this->student();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->subMinute()->toIso8601String()]);
        $this->get(route('student.login'))->assertOk();
        $student->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now(), 'two_factor_version' => 'home-proof'])->save();
        $this->withSession(['student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])->get(route('student.login'))->assertOk();
        $this->withSession(['student.two_factor_verified' => app(StudentTwoFactorService::class)->fingerprint($student)])->get(route('student.login'))->assertRedirect(route('student.home'));
        Auth::guard('student')->logout();
    }

    private function student(): Student
    {
        return Student::factory()->verified()->create(['first_name' => 'Amina', 'last_name' => 'Learner', 'name_normalized' => 'amina learner', 'email' => 'amina@example.test', 'email_normalized' => 'amina@example.test']);
    }

    /** @return array<string,string> */
    private function credentials(Student $student): array
    {
        return ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => $student->email_normalized];
    }
}
