<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\AnnouncementService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentTwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StudentShellTest extends TestCase
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

    public function test_authenticated_html_destinations_share_navigation_and_preserve_unread_ownership(): void
    {
        $student = $this->student();
        $own = StudentNotification::factory()->create(['student_id' => $student->id, 'title' => 'Your next practice', 'message' => 'Revisit your Arabic notes before your next lesson.', 'read_at' => null]);
        StudentNotification::factory()->create(['student_id' => $student->id, 'read_at' => now()]);
        StudentNotification::factory()->create(['title' => 'Another student private notice', 'read_at' => null]);
        Booking::factory()->create(['student_id' => $student->id]);
        StudentBin::factory()->create(['student_id' => $student->id, 'title' => 'مَرْحَبًا • Everyday greetings', 'body' => 'أَهْلًا وَسَهْلًا — Welcome. Review these greetings before your next lesson.', 'student_visible' => true]);
        Setting::set('whatsapp_portal', true);
        Setting::set('whatsapp_url', 'https://wa.me/12025550142');
        $this->signIn($student);

        foreach ([
            ['student.home', 'Home', 'home'],
            ['student.dashboard', 'Sessions', 'learning'],
            ['student.learning.index', 'My Learning', 'learning'],
            ['student.teaching.index', 'Practice & Feedback', 'learning'],
            ['student.scheduling.index', 'Planning', 'personal'],
            ['student.bins.index', 'Notes', 'personal'],
            ['student.bookmarks.index', 'Bookmarks', 'personal'],
            ['student.feedback.index', 'Feedback', 'personal'],
            ['student.statements.index', 'Statements & Receipts', 'records'],
            ['student.profile', 'Profile', 'account'],
            ['student.security.show', 'Account Security', 'account'],
            ['student.video.devices', 'Authorized Browsers', 'account'],
            ['student.notifications.index', 'Notifications', 'utility'],
        ] as [$route, $label, $group]) {
            $response = $this->getRenderedPage(route($route))->assertOk()->assertSee('Skip to main content')->assertDontSee('Another student private notice');
            $xpath = $this->document($response);
            $this->assertSame(1, $xpath->query('//main[@id="student-main"]//h1')->length);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Student mobile navigation"]')->length);
            $this->assertSame(10, $xpath->query('//nav[@aria-label="Student navigation"]//a')->length);
            $this->assertSame(5, $xpath->query('//nav[@aria-label="Student mobile navigation"]//a | //nav[@aria-label="Student mobile navigation"]//button')->length);
            $this->assertSame(3, $xpath->query('//form[@action="'.route('student.logout').'"][@method="POST"]/input[@name="_token"]')->length);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Student utilities"]//a/span[@class="student-unread"]/span[normalize-space()="'.($route === 'student.home' ? '1' : '2').' unread"]')->length);
            $this->assertSame(in_array($group, ['personal', 'records', 'account'], true) ? 1 : 0, $xpath->query('//button[@data-student-more][@data-current-section]')->length);
            $this->assertSame(in_array($group, ['personal', 'records', 'account'], true) ? 1 : 0, $xpath->query('//span[@id="student-more-context"]')->length);
            $this->assertSame(0, $xpath->query('//button[@data-student-more][@aria-current]')->length);
            $currentLinks = $xpath->query('//a[@href="'.route($route).'"][@aria-current="page"]');
            $this->assertGreaterThan(0, $currentLinks->length, $label);
            $this->assertSame(0, $xpath->query('//a[@aria-current][@href!="'.route($route).'"]')->length, $label);
            $this->capture($route, $response);
        }

        $this->assertNull($own->fresh()->read_at);
        $this->assertDatabaseCount('student_notifications', 4);
        $this->assertDatabaseCount('lms_learning_visits', 0);
    }

    public function test_nested_learning_and_session_pages_identify_the_parent_location(): void
    {
        $student = $this->student();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        $course = Course::factory()->published()->create(['title' => 'Arabic foundations']);
        $section = Section::factory()->published()->create(['course_id' => $course->id, 'title' => 'Everyday Arabic']);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id, 'title' => 'أَهْلًا • Greetings']);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p lang="ar" dir="rtl">أَهْلًا وَسَهْلًا</p><p>Practice a warm greeting, then return to your lesson notes.</p>']]);
        app(LmsAccessOperations::class)->grant(Administrator::factory()->create(), $student, $course, [], 'shell-learning-fixture');
        $this->signIn($student);

        foreach ([
            ['student.bookings.show', [$booking], 'student.dashboard'],
            ['student.learning.courses.show', [$course], 'student.learning.index'],
            ['student.learning.lessons.show', [$course, $lesson], 'student.learning.index'],
            ['student.learning.notes.index', [], 'student.learning.index'],
        ] as [$route, $parameters, $parent]) {
            $response = $this->getRenderedPage(route($route, $parameters))->assertOk();
            $xpath = $this->document($response);
            $this->assertGreaterThan(0, $xpath->query('//a[@href="'.route($parent).'"][@aria-current="location"]')->length);
            $this->assertSame(0, $xpath->query('//nav[@aria-label="Student navigation" or @aria-label="Student mobile navigation"]//a[@aria-current="page"]')->length);
            $this->capture($route, $response);
        }
    }

    #[TestWith(['student.bookings.reschedule', 'student.dashboard'])]
    #[TestWith(['student.lessons.show', 'student.dashboard'])]
    #[TestWith(['student.forms.show', 'student.home'])]
    #[TestWith(['student.learning.assignments.show', 'student.learning.index'])]
    #[TestWith(['student.evidence.show', 'student.learning.index'])]
    #[TestWith(['student.progress-reports.show', 'student.teaching.index'])]
    #[TestWith(['student.bins.edit', 'student.bins.index'])]
    public function test_nested_route_context_survives_independent_page_content(string $route, string $parent): void
    {
        $this->signIn($this->student());
        Route::get('/shell-context-fixture', fn () => view('layouts.student'))->middleware('web')->name($route);

        $response = $this->getRenderedPage('/shell-context-fixture')->assertOk();

        $this->assertSame(1, $this->document($response)->query('//nav[@aria-label="Student navigation"]//a[@href="'.route($parent).'"][@aria-current="location"]')->length);
    }

    #[TestWith(['expired'])]
    #[TestWith(['missing_guard'])]
    #[TestWith(['missing_two_factor'])]
    public function test_invalid_student_sessions_cannot_render_authenticated_navigation(string $state): void
    {
        $student = $this->student();
        $this->signIn($student);
        if ($state === 'expired') {
            $this->withSession(['student_auth_expires_at' => now('UTC')->subMinute()->toIso8601String()]);
        } elseif ($state === 'missing_guard') {
            Auth::guard('student')->logout();
        } else {
            $student->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now(), 'two_factor_version' => 'shell-version'])->save();
        }

        $this->getRenderedPage(route('student.dashboard'))->assertRedirect(route('student.login'));
        $response = $this->getRenderedPage(route('student.login'))->assertOk();
        $this->assertNoAuthenticatedNavigation($response);
    }

    public function test_guest_and_pending_two_factor_screens_stay_focused(): void
    {
        $response = $this->getRenderedPage(route('student.login'))->assertOk();
        $this->assertNoAuthenticatedNavigation($response);
        $this->capture('guest', $response);
        $student = $this->student();
        $student->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now(), 'two_factor_version' => 'shell-version'])->save();
        $this->withSession(['student.two_factor_pending' => ['id' => $student->id, 'fingerprint' => app(StudentTwoFactorService::class)->fingerprint($student), 'expires_at' => now('UTC')->addMinutes(10)->timestamp]]);

        $response = $this->getRenderedPage(route('student.two-factor.challenge'))->assertOk()->assertSee('Verify your sign-in');
        $this->assertNoAuthenticatedNavigation($response);
        $this->capture('two-factor', $response);
    }

    public function test_staff_preview_hides_student_shell_even_when_student_guard_is_also_authenticated(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $this->actingAs(Administrator::factory()->create(['role' => 'admin']), 'web');

        $response = $this->getRenderedPage(route('admin.students.preview-portal', $student))->assertOk()->assertSee('Read-Only Student Portal Preview')->assertSee('Exit Preview');
        $this->assertNoAuthenticatedNavigation($response);
        $this->assertSame(0, $this->document($response)->query('//a[@data-whatsapp-cta]')->length);
        $this->capture('preview', $response);
    }

    public function test_financial_details_share_records_context_and_keep_exports_and_admin_rendering(): void
    {
        $student = $this->student();
        $package = StudentPackage::factory()->create(['student_id' => $student->id, 'package_name' => 'Arabic lesson practice package']);
        $payment = PaymentRecord::factory()->create(['student_package_id' => $package->id]);
        $this->signIn($student);

        foreach ([['student.statements.show', [$package]], ['student.receipts.show', [$package, $payment->id]]] as [$route, $parameters]) {
            $response = $this->getRenderedPage(route($route, $parameters))->assertOk()->assertSeeText('240.00 USD')->assertSee('Print / Save PDF')->assertSee('Statement CSV')->assertSee('Statement XLSX');
            $this->assertSame(3, $this->document($response)->query('//a[@href="'.route('student.statements.index').'"][@aria-current="location"]')->length);
            $this->capture($route, $response);
        }
        $this->actingAs(Administrator::factory()->create(), 'web');
        $admin = $this->getRenderedPage(route('admin.statements.show', $package))->assertOk()->assertSee('240.00 USD')->assertDontSee('Student mobile navigation');
        $this->capture('admin-statement', $admin);
        $this->assertDatabaseCount('payment_records', 1);
    }

    public function test_sign_out_retains_post_semantics_and_clears_the_student_session(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $this->getRenderedPage(route('student.logout'))->assertMethodNotAllowed();

        $this->post(route('student.logout'))->assertRedirect(route('student.login'))->assertSessionMissing('student_id')->assertSessionMissing('student_auth_expires_at');
        $this->assertGuest('student');
    }

    public function test_student_announcement_stays_in_the_content_column(): void
    {
        app(AnnouncementService::class)->save(['enabled' => true, 'audience' => 'public_student', 'message_en' => 'Synthetic notice: check your lesson timezone before booking.', 'severity' => 'information', 'dismissible' => true]);
        $this->signIn($this->student());

        $response = $this->getRenderedPage(route('student.dashboard'))->assertOk()->assertSee('Synthetic notice: check your lesson timezone before booking.');

        $this->assertSame(1, $this->document($response)->query('//div[@class="student-column"]/aside[@data-site-announcement]')->length);
        $this->capture('student-announcement', $response);
    }

    public function test_public_rendering_does_not_receive_the_student_shell(): void
    {
        $response = $this->getRenderedPage(route('home'))->assertOk();

        $this->assertNoAuthenticatedNavigation($response);
        $this->assertSame(0, $this->document($response)->query('//body[contains(@class,"student-portal")]')->length);
        $this->capture('public-home', $response);
    }

    private function student(): Student
    {
        return Student::factory()->verified()->create(['first_name' => 'Amina', 'last_name' => 'أمينة — Synthetic Portal Learner', 'email' => 'amina.portal@example.test', 'preferred_timezone' => 'Africa/Cairo']);
    }

    private function signIn(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String(), 'student_display_timezone' => 'Africa/Cairo']);
    }

    private function getRenderedPage(string $url): TestResponse
    {
        Livewire::flushState();

        return $this->get($url);
    }

    private function document(TestResponse $response): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        return new \DOMXPath($document);
    }

    private function assertNoAuthenticatedNavigation(TestResponse $response): void
    {
        $this->assertSame(0, $this->document($response)->query('//nav[@aria-label="Student navigation" or @aria-label="Student utilities" or @aria-label="Student mobile navigation"] | //dialog | //form[@action="'.route('student.logout').'"]')->length);
    }

    private function capture(string $name, TestResponse $response): void
    {
        $path = getenv('STUDENT_SHELL_EVIDENCE_PATH');
        if (! is_string($path) || $path === '') {
            return;
        }
        $base = realpath(storage_path(str_contains($path, 'student-portal-phase03-evidence') ? 'app/private/student-portal-phase03-evidence' : (str_contains($path, 'student-portal-phase02-evidence') ? 'app/private/student-portal-phase02-evidence' : 'app/private/student-portal-phase01-evidence')));
        $resolved = realpath($path);
        if (! $base || ! $resolved || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Evidence must stay in the authorized private run directory.');
        }
        $html = preg_replace('/(data-csrf=")[^"]*(")/i', '$1REDACTED$2', $response->getContent());
        file_put_contents($resolved.'/'.$name.'.html', preg_replace('/(<(?:input|meta)\b[^>]*\bname="(?:_token|csrf-token)"[^>]*?\b(?:value|content)=")[^"]*(")/i', '$1REDACTED$2', $html));
        file_put_contents($resolved.'/responses.jsonl', json_encode(['file' => $name.'.html', 'url' => request()->fullUrl(), 'status' => $response->getStatusCode(), 'environment' => app()->environment(), 'database' => config('database.connections.mysql.database'), 'fixture' => 'synthetic; transactional rollback after each test']).PHP_EOL, FILE_APPEND);
    }
}
