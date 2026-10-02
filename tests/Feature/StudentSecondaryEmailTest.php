<?php

namespace Tests\Feature;

use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Resources\Services\EmailQualityService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentEmail;
use App\Mail\StudentSecondaryEmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudentSecondaryEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->partialMock(EmailQualityService::class)->shouldReceive('dnsRecords')->andReturn([['type' => 'MX', 'target' => 'mail.routable.example']]);
    }

    public function test_verification_is_owned_single_use_and_enables_existing_two_identifier_login(): void
    {
        $student = Student::factory()->verified()->create();
        $this->studentSession($student);
        $this->post(route('student.profile.email.request'), ['email' => 'SECONDARY@routable.example'])->assertSessionHasNoErrors();
        $url = '';
        Mail::assertSent(StudentSecondaryEmailVerification::class, function ($mail) use (&$url): bool {
            $url = $mail->verificationUrl;

            return true;
        });
        $other = Student::factory()->verified()->create();
        $this->studentSession($other);
        $this->get($url)->assertSessionHasErrors('email');
        $this->assertDatabaseCount('student_emails', 0);
        $this->studentSession($student);
        $this->get($url)->assertRedirect(route('student.profile'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_emails', ['student_id' => $student->id, 'email_normalized' => 'secondary@routable.example']);
        $this->get($url)->assertSessionHasErrors('email');
        $this->post(route('student.logout'));
        $this->post(route('student.login.submit'), ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => 'secondary@routable.example'])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_expired_and_conflicting_secondary_emails_are_rejected(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $this->studentSession($student);
        $this->post(route('student.profile.email.request'), ['email' => $other->email])->assertSessionHasErrors('email');
        $this->post(route('student.profile.email.request'), ['email' => 'expire@routable.example'])->assertSessionHasNoErrors();
        $url = '';
        Mail::assertSent(StudentSecondaryEmailVerification::class, function ($mail) use (&$url): bool {
            $url = $mail->verificationUrl;

            return true;
        });
        $this->travel(16)->minutes();
        $this->get($url)->assertSessionHasErrors('email');
        $this->assertDatabaseCount('student_emails', 0);
        $this->assertDatabaseCount('student_email_verifications', 1);
    }

    public function test_five_resource_requests_reuse_one_student_and_contact_without_trusting_an_unverified_email(): void
    {
        $student = Student::factory()->verified()->create(['email' => 'primary@routable.example', 'email_normalized' => 'primary@routable.example']);
        StudentEmail::create(['student_id' => $student->id, 'email_normalized' => 'secondary@routable.example', 'verified_at' => now('UTC')]);
        $category = ResourceCategory::create(['name' => 'QA', 'slug' => 'qa', 'active' => true]);
        $this->studentSession($student);
        $this->withHeader('User-Agent', 'Mozilla/5.0 Secondary Email QA');
        for ($i = 0; $i < 5; $i++) {
            $resource = Resource::create(['title' => 'Identity QA '.$i, 'slug' => 'identity-qa-'.$i, 'category_id' => $category->id, 'status' => 'published', 'published_at' => now()->subMinute(), 'is_gated' => true, 'gate_mode' => 'A', 'external_url' => 'https://example.org/guide.pdf', 'file_type' => 'pdf']);
            $page = $this->get(route('resources.show', $resource->slug))->assertOk()->assertSee('primary@routable.example');
            if ($i === 0) {
                $this->withCredentials()->withCookies(['_va_visitor' => $page->getCookie('_va_visitor')->getValue(), '_va_session' => $page->getCookie('_va_session')->getValue()]);
            }
            $this->postJson(route('resources.request', $resource->slug), ['name' => $student->name, 'email' => $i % 2 ? 'SECONDARY@routable.example' : 'primary@routable.example'])->assertOk()->assertJsonPath('requires_pin', false);
        }
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('resource_requests', 5);
        $this->assertSame(5, ResourceRequest::where('student_id', $student->id)->count());
        $this->postJson(route('resources.request', 'identity-qa-0'), ['name' => $student->name, 'email' => 'unverified@routable.example'])->assertOk();
        $this->assertDatabaseCount('student_emails', 1);
        $this->post(route('student.logout'));
        $this->post(route('student.login.submit'), ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => 'unverified@routable.example'])->assertSessionHasErrors();
        Mail::assertNothingOutgoing();
    }

    private function studentSession(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }
}
