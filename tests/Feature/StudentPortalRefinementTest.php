<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\RichTextSanitizer;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsContentService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentBookmark;
use App\Domains\Students\Models\StudentErrorLog;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StudentPortalRefinementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00 UTC');
        fake()->seed(10126);
        Http::preventStrayRequests();
        $this->withHeader('X-Analytics-Synthetic', '1');
        URL::forceRootUrl('http://arabicwabdallah.test');
    }

    public function test_representative_owned_states_render_without_completing_learning(): void
    {
        Mail::fake();
        Notification::fake();
        $student = Student::factory()->verified()->create(['first_name' => 'Amina', 'last_name' => 'أمينة — Synthetic Learner', 'email' => 'phase01b@example.test', 'preferred_timezone' => 'Africa/Cairo']);
        $this->signIn($student);
        Setting::set('whatsapp_portal', true);
        Setting::set('whatsapp_url', 'https://wa.me/12025550142');
        $this->page('sessions-new', route('student.dashboard'));
        foreach (['student.bins.index' => 'notes-empty', 'student.teaching.index' => 'practice-empty', 'student.learning.index' => 'learning-empty', 'student.notifications.index' => 'notifications-empty', 'student.bookmarks.index' => 'bookmarks-empty'] as $route => $name) {
            $this->page($name, route($route));
        }
        StudentBin::factory()->create(['student_id' => $student->id, 'title' => 'مُرَاجَعَة • Greeting practice', 'body' => "أَهْلًا وَسَهْلًا — Welcome, Amina (lesson 2).\nKeep the final vowel clear.", 'student_visible' => true]);
        StudentBin::factory()->create(['student_id' => $student->id, 'created_by_type' => 'student', 'created_by_id' => $student->id, 'title' => 'My bilingual revision', 'body' => "مُرَاجَعَة ٣ — Revision 3.\nKeep practising the greeting.", 'student_visible' => true]);
        $noteOnly = $this->page('home-note-only', route('student.home'));
        $progress = $this->document($noteOnly->getContent());
        $this->assertSame(0, $progress->query('//section[@aria-label="Learning progress"]//dl')->length);
        $noteOnly->assertSee('View Learning Notes')->assertSee('(2)');
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Personal Arabic lessons', 4, '1234.56', '0.00', 'USD', null, 'phase01b-package', entitlementCode: 'one_hour');
        $payment = $ledger->recordPayment($package, '240.00', 'phase01b-payment-one', null, 'International bank transfer — recorded manually', 'SYNTHETIC-REFERENCE-ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $ledger->recordPayment($package, '160.00', 'phase01b-payment-two', null, '', null);
        $ledger->refund($payment, '25.50', 'phase01b-refund', null, 'Synthetic correction');
        app(InstallmentScheduleService::class)->create($package, [['expected_amount' => '400.00', 'due_date' => '2026-10-20'], ['expected_amount' => '834.56', 'due_date' => '2026-11-20']], 'phase01b-installments', null);
        $type = $this->oneHourPackageLesson();
        AvailabilityRule::query()->create(['weekday' => 4, 'start_time' => '10:00', 'end_time' => '16:00', 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);
        app(StudentBookingService::class)->createForAdministrator($student, $type, CarbonImmutable::parse('2026-11-05 08:00:00 UTC'), 'Africa/Cairo', 'phase01b-upcoming', Administrator::factory()->create()->id);
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'assigned', 'title' => 'Practise a short introduction', 'instructions' => 'Read your greeting aloud, then write two sentences.']);
        ResourceAssignment::factory()->create(['student_id' => $student->id, 'resource_id' => ResourceFactory::new()->create(['title' => 'Everyday greetings — مَرْحَبًا'])->id, 'student_visible' => true, 'instructions' => 'Review these greetings before your lesson.']);
        $this->page('sessions-active', route('student.dashboard'));
        foreach (['student.teaching.index' => 'practice', 'student.scheduling.index' => 'planning', 'student.bins.index' => 'notes', 'student.profile' => 'profile'] as $route => $name) {
            $this->page($name, route($route));
        }
        StudentUnavailability::factory()->create(['student_id' => $student->id, 'timezone' => 'Africa/Cairo']);
        BookingWaitlist::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id, 'timezone' => 'Africa/Cairo', 'preferred_time_from' => '10:00', 'preferred_time_to' => '12:00']);
        $this->page('planning-saved', route('student.scheduling.index'))->assertSeeText('Cancel period')->assertSeeText('Withdraw interest');
        $course = Course::factory()->published()->create(['title' => 'Arabic foundations • أُسُس العَرَبِيَّة']);
        $section = Section::factory()->published()->create(['course_id' => $course->id, 'title' => 'Everyday Arabic']);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id, 'title' => 'أَهْلًا • Greetings (lesson 2)']);
        $html = '<h2 lang="ar" dir="rtl">مَرْحَبًا بِكَ</h2><p lang="ar" dir="rtl">أَهْلًا وَسَهْلًا، يا Amina (2).</p><p lang="en" dir="ltr">Practise a warm greeting, then return to your lesson notes.</p><p>السَّلَامُ عَلَيْكُمْ — مَرْحَبًا بِكُمْ.</p><p>This English paragraph follows Arabic and ends here.</p><p>Read <span lang="ar" dir="rtl">أَهْلًا وَسَهْلًا</span> aloud (3 times).</p><ul><li>First, listen to the greeting.</li><li>ثُمَّ كَرِّرِ التَّحِيَّةَ.</li></ul>';
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => app(LmsContentService::class)->sanitizeText($html)]]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>Start with English, then read Arabic.</p><p>أَهْلًا وَسَهْلًا بِكُمْ.</p>']]);
        app(LmsAccessOperations::class)->grant(Administrator::factory()->create(), $student, $course, [], 'phase01b-learning');
        LessonNote::factory()->create(['student_id' => $student->id, 'lesson_id' => $lesson->id, 'body' => "Amina أمينة — practise (2).\nأَهْلًا وَسَهْلًا"]);
        $this->page('learning', route('student.learning.index'));
        $lessonPage = $this->page('lesson', route('student.learning.lessons.show', [$course, $lesson]));
        $content = $this->document($lessonPage->getContent());
        $this->assertSame(1, $content->query('//p[@lang="ar"][@dir="rtl"][contains(.,"Amina")]')->length);
        $this->assertSame(1, $content->query('//p[@lang="en"][@dir="ltr"][contains(.,"Practise a warm greeting")]')->length);
        $this->assertSame(1, $content->query('//p[@dir="auto"][contains(.,"This English paragraph")]')->length);
        $this->assertSame(1, $content->query('//p[@dir="auto"][contains(.,"Start with English")]')->length);
        $this->page('statement', route('student.statements.show', $package));
        $this->page('receipt', route('student.receipts.show', [$package, $payment->id]));
        for ($index = 0; $index < 18; $index++) {
            $extra = $ledger->recordPayment($package, '10.00', 'phase03-history-'.$index, null, $index % 2 === 0 ? 'International bank transfer' : '', $index % 2 === 0 ? 'SYNTHETIC-REFERENCE-ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' : null);
            $ledger->refund($extra, '1.00', 'phase03-history-refund-'.$index, null, 'Synthetic correction');
        }
        $this->page('statement-multipage', route('student.statements.show', $package));
        $this->assertSame(0, DB::table('lms_lesson_progress')->where('student_id', $student->id)->whereNotNull('completed_at')->count());
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'completed', 'title' => 'Completed greeting practice']);
        StudentErrorLog::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'improved', 'mistake' => 'Final vowels', 'correction' => 'Keep the vowel audible.']);
        $this->page('practice-populated', route('student.teaching.index'))->assertSeeText('1 / 2')->assertSeeText('1 / 1');
        foreach (['in_progress', 'submitted', 'reviewed'] as $status) {
            Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => $status, 'title' => 'Synthetic practice '.$status, 'feedback' => $status === 'reviewed' ? 'Read the final vowel clearly. أَحْسَنْتِ' : null]);
        }
        foreach (['practising', 'resolved'] as $status) {
            StudentErrorLog::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => $status, 'mistake' => 'Synthetic pronunciation practice', 'correction' => 'Read the greeting aloud.']);
        }
        $this->page('practice-all-statuses', route('student.teaching.index'))->assertSeeText('Not Started')->assertSeeText('In Progress')->assertSeeText('Submitted')->assertSeeText('Reviewed')->assertSeeText('Completed')->assertSeeText('Working On It')->assertSeeText('Improving')->assertDontSeeText('Status Unavailable');
        StudentBookmark::factory()->create(['student_id' => $student->id, 'kind' => 'note', 'target_id' => StudentBin::query()->where('student_id', $student->id)->firstOrFail()->id]);
        $this->page('bookmarks-populated', route('student.bookmarks.index'))->assertSeeText('مُرَاجَعَة');
        StudentNotification::factory()->create(['student_id' => $student->id, 'title' => 'Synthetic practice reminder', 'message' => 'Review your greeting before the lesson.']);
        $this->page('notifications-populated', route('student.notifications.index'))->assertSeeText('Synthetic practice reminder');
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    #[TestWith([[], [], false])]
    #[TestWith([['shared_notes' => 1], [], false])]
    #[TestWith([['homework_total' => 3], ['Homework completed' => '0 / 3'], false])]
    #[TestWith([['completed_lessons' => 2, 'milestones_completed' => 1, 'milestones_total' => 3, 'errors_total' => 2], ['Completed lessons' => '2', 'Milestones completed' => '1 / 3', 'Patterns improved or resolved' => '0 / 2'], false])]
    #[TestWith([['homework_completed' => 2], ['Homework completed' => '2 / 0'], true])]
    public function test_progress_shows_only_measured_or_inconsistent_categories(array $values, array $expected, bool $inconsistent): void
    {
        $progress = array_replace(['completed_lessons' => 0, 'shared_notes' => 0, 'milestones_completed' => 0, 'milestones_total' => 0, 'homework_completed' => 0, 'homework_total' => 0, 'errors_improved' => 0, 'errors_total' => 0], $values);
        $html = Blade::render('<x-student-progress :progress="$progress" />', ['progress' => $progress]);
        $document = $this->document($html);
        $this->assertSame(count($expected), $document->query('//dl/div')->length);
        foreach ($expected as $label => $value) {
            $this->assertSame($value, trim($document->query('//dl/div[dt="'.$label.'"]/dd')->item(0)->textContent));
        }
        $this->assertSame($expected === [], str_contains($html, 'Your progress will appear here'));
        $this->assertSame($inconsistent, str_contains($html, 'These records need review'));
        $this->assertSame(1, $document->query('//a[@href="'.route('student.bins.index').'"]')->length);
        if ($progress['shared_notes'] > 0) {
            $this->assertStringContainsString('(1)', $html);
        }
        $preview = Blade::render('<x-student-progress :progress="$progress" :read-only="true" />', ['progress' => $progress]);
        $this->assertSame(0, $this->document($preview)->query('//a')->length);
    }

    public function test_lesson_profile_preserves_valid_direction_and_language_without_relaxing_cms_safety(): void
    {
        $sanitizer = app(RichTextSanitizer::class);
        $input = '<p dir="rtl" lang="ar" onclick="alert(1)">أَهْلًا</p><p dir="auto" lang="en-US">Amina أمينة (2).</p><p dir="bad" lang="javascript:alert(1)">English.</p><blockquote dir="ltr"><p>Authored group.</p></blockquote><blockquote dir="auto"><p>أَهْلًا</p><p>Independent English.</p></blockquote><p>Read <span dir="rtl" lang="ar">مَرْحَبًا</span>.</p><a href="javascript:alert(1)" onmouseover="bad()">Unsafe link</a><script>alert(1)</script><iframe src="https://example.test"></iframe><img src="/storage/test.png" onerror="bad()"><p style="color:red" id="test">Final sentence.</p>';
        $clean = $sanitizer->sanitizeLesson($input);
        $document = $this->document($clean);
        $this->assertSame(1, $document->query('//p[@dir="rtl"][@lang="ar"]')->length);
        $this->assertSame(1, $document->query('//p[@dir="auto"][@lang="en-us"]')->length);
        $this->assertSame(1, $document->query('//p[@dir="auto"][normalize-space()="English."][not(@lang)]')->length);
        $this->assertSame(1, $document->query('//blockquote[@dir="ltr"]/p[not(@dir)]')->length);
        $this->assertSame(2, $document->query('//blockquote[@dir="auto"]/p[@dir="auto"]')->length);
        $this->assertSame(1, $document->query('//span[@dir="rtl"][@lang="ar"]')->length);
        $this->assertSame(0, $document->query('//script | //iframe | //img | //*[@onclick or @onmouseover or @onerror or @style or @id] | //a[@href]')->length);
        $this->assertStringContainsString('أَهْلًا', $clean);
        $this->assertStringContainsString('Final sentence.', $clean);
        $this->assertSame($clean, $sanitizer->sanitizeLesson($clean));
        $public = $this->document($sanitizer->sanitize('<p dir="rtl" lang="ar">أَهْلًا</p>'));
        $this->assertSame(0, $public->query('//*[@dir or @lang]')->length);
    }

    public function test_financial_records_keep_local_references_receipt_scope_refund_relationships_and_exports(): void
    {
        $foreign = StudentPackage::factory()->create();
        PaymentRecord::factory()->create(['student_package_id' => $foreign->id]);
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Africa/Cairo']);
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Owned purchase', 2, '1234.56', '0.00', 'USD', null, 'finance-package', entitlementCode: 'one_hour');
        $first = $ledger->recordPayment($package, '240.00', 'finance-first', null, '', null);
        $second = $ledger->recordPayment($package, '160.00', 'finance-second', null, 'International bank transfer', 'SYNTHETIC-REFERENCE');
        $ledger->refund($first, '25.50', 'finance-refund', null);
        app(InstallmentScheduleService::class)->create($package, [['expected_amount' => '1234.56', 'due_date' => '2026-11-20']], 'finance-schedule', null);
        $this->signIn($student);

        $statement = $this->page('financial-contract', route('student.statements.show', $package))->assertSee('Purchase #1')->assertSee('Payment #1')->assertSee('Payment #2')->assertSee('Refund #1')->assertSee('Original payment #1')->assertSee('Manual payment')->assertSee('Not recorded')->assertSee('International bank transfer')->assertSee('SYNTHETIC-REFERENCE')->assertSee('Expected Installments')->assertSee('26 Oct 2026, 12:00 PM');
        foreach (['1,234.56', '240.00', '160.00', '25.50', '374.50', '860.06', 'USD'] as $value) {
            $statement->assertSeeText($value);
        }
        $this->assertSame(8, $this->document($statement->getContent())->query('//th[@scope="col"]')->length);
        $this->get(route('student.receipts.show', [$package, $second->id]))->assertOk()->assertSee('Payment #2')->assertSee('No Refunds Recorded.')->assertDontSee('Refund #1')->assertDontSee('Payment #1')->assertDontSee('Expected Installments');
        $this->get(route('student.receipts.show', [$package, $first->id]))->assertOk()->assertSee('Payment #1')->assertSee('Original payment #1')->assertSee('Refund #1')->assertDontSee('Payment #2')->assertDontSee('SYNTHETIC-REFERENCE');
        $csvResponse = $this->get(route('student.statements.export', $package))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString('Original payment #1', $csv);
        $this->assertStringContainsString('25.50', $csv);
        $csvRows = array_map(fn (string $line): array => str_getcsv($line, escape: ''), explode("\n", trim(substr($csv, 3))));
        $this->assertSame(['Record', 'Your reference', 'Date (Africa/Cairo)', 'Amount', 'Currency', 'Description / method', 'Recorded external reference'], $csvRows[0]);
        $xlsxResponse = $this->get(route('student.statements.export', [$package, 'format' => 'xlsx']))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $xlsxFile = $xlsxResponse->baseResponse->getFile()->getPathname();
        try {
            $workbook = IOFactory::load($xlsxFile);
            $xlsxRows = $workbook->getActiveSheet()->toArray();
            $workbook->disconnectWorksheets();
            $this->assertSame($csvRows, array_map(fn (array $row): array => array_map(fn (mixed $cell): string => $cell === null ? '' : (string) $cell, $row), $xlsxRows));
            foreach ([$csvResponse, $xlsxResponse] as $export) {
                $this->assertStringContainsString('private', $export->headers->get('Cache-Control'));
                $this->assertStringContainsString('no-store', $export->headers->get('Cache-Control'));
                $this->assertStringContainsString('attachment;', $export->headers->get('Content-Disposition'));
            }
            $path = getenv('STUDENT_REFINEMENT_EVIDENCE_PATH');
            $base = realpath(storage_path('app/private/student-portal-phase03-closure'));
            $resolved = is_string($path) ? realpath($path) : false;
            if ($resolved && $base && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR)) {
                file_put_contents(dirname($resolved).'/exports/statement.csv', $csv);
                copy($xlsxFile, dirname($resolved).'/exports/statement.xlsx');
                $metadata = [];
                foreach (['csv' => $csvResponse, 'xlsx' => $xlsxResponse] as $format => $export) {
                    foreach (['Content-Type', 'Content-Disposition', 'Cache-Control'] as $header) {
                        $metadata[$format][$header] = $export->headers->get($header);
                    }
                }
                file_put_contents(dirname($resolved).'/exports/response-metadata.json', json_encode(['method' => 'Isolated Laravel request response bodies; not browser downloads', 'headers' => $metadata, 'rows' => $xlsxRows], JSON_PRETTY_PRINT));
            }
        } finally {
            if (is_file($xlsxFile)) {
                unlink($xlsxFile);
            }
        }
        foreach (['csv', 'xlsx'] as $format) {
            $this->get(route('student.statements.export', [$foreign, 'format' => $format]))->assertNotFound();
        }
        $this->get(route('student.statements.show', $foreign))->assertNotFound();
        $this->get(route('student.receipts.show', [$package, $foreign->payments->first()->id]))->assertNotFound();
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.statements.show', $package))->assertOk()->assertSee('Purchase #'.$package->id)->assertSee('Actual payment history')->assertSee('240.00 USD')->assertDontSee('student-financial-stacked');
    }

    public function test_notes_keep_get_search_clear_edit_state_and_associated_validation_errors(): void
    {
        $student = Student::factory()->verified()->create();
        $note = StudentBin::factory()->create(['student_id' => $student->id, 'created_by_type' => 'student', 'created_by_id' => $student->id, 'title' => 'Vocabulary revision', 'body' => "أَهْلًا\nAmina (2)."]);
        StudentBin::factory()->create(['student_id' => $student->id, 'title' => 'Different topic']);
        StudentBin::factory()->create(['title' => 'Foreign vocabulary note']);
        $this->signIn($student);

        $search = $this->get(route('student.bins.index', ['q' => 'Vocabulary']))->assertOk()->assertSee('Vocabulary revision')->assertDontSee('Different topic')->assertDontSee('Foreign vocabulary note');
        $document = $this->document($search->getContent());
        $this->assertSame(1, $document->query('//form[@method="GET"]//input[@name="q"][@value="Vocabulary"]')->length);
        $this->assertSame(1, $document->query('//a[@href="'.route('student.bins.index').'"][normalize-space()="Clear Search"]')->length);
        $edit = $this->get(route('student.bins.edit', $note))->assertOk()->assertSee('Edit Note')->assertSee('Vocabulary revision');
        $this->assertSame(1, $this->document($edit->getContent())->query('//form[@method="POST"][@action="'.route('student.bins.update', $note).'"]/input[@name="_method"][@value="PUT"]')->length);
        $this->post(route('student.bins.store'), ['title' => '', 'body' => ''])->assertSessionHasErrors(['title', 'body']);
        $html = view('student.educational-bins', ['student' => $student, 'portal' => true, 'bins' => collect(), 'filters' => [], 'editing' => null, 'errors' => session('errors')])->render();
        $document = $this->document($html);
        $this->assertSame(1, $document->query('//input[@name="title"][@aria-invalid="true"][@aria-describedby="note-title-error"]')->length);
        $this->assertSame(1, $document->query('//textarea[@name="body"][@aria-invalid="true"][@aria-describedby="note-body-error"]')->length);
        $this->assertSame(2, $document->query('//*[@id="note-title-error" or @id="note-body-error"]')->length);
        $this->assertDatabaseCount('student_bins', 3);
        $body = "أَهْلًا وَسَهْلًا — Welcome, Amina (lesson 2).\nKeep the final vowel clear. السَّلَامُ عَلَيْكُمْ";
        $this->post(route('student.bins.store'), ['title' => 'My bilingual note', 'body' => $body])->assertRedirect()->assertSessionHasNoErrors();
        $saved = StudentBin::query()->where('student_id', $student->id)->where('title', 'My bilingual note')->sole();
        $this->assertSame($body, $saved->body);
        $this->get(route('student.bins.edit', $saved))->assertOk()->assertSee('أَهْلًا وَسَهْلًا')->assertSee('Welcome, Amina (lesson 2).');
        $revised = $body."\nمُرَاجَعَة ٣ — Revision 3.";
        $this->put(route('student.bins.update', $saved), ['title' => 'My bilingual note', 'body' => $revised, 'revision' => $saved->revision])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($revised, $saved->fresh()->body);
    }

    public function test_saved_timezone_is_owned_and_persistent_without_moving_an_appointment(): void
    {
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Africa/Cairo']);
        $other = Student::factory()->verified()->create(['preferred_timezone' => 'Africa/Cairo']);
        $booking = Booking::factory()->create(['student_id' => $student->id, 'start_at_utc' => '2026-11-05 08:00:00', 'end_at_utc' => '2026-11-05 09:00:00']);
        $original = $booking->only(['start_at_utc', 'end_at_utc', 'timezone', 'status']);
        $this->signIn($student);
        $this->from(route('student.profile'))->patch(route('student.profile.timezone'), ['timezone' => 'America/New_York', 'student_id' => $other->id])
            ->assertRedirect(route('student.profile'))->assertSessionHasNoErrors()->assertSessionHas('student_display_timezone', 'America/New_York');
        $this->assertSame('America/New_York', $student->fresh()->preferred_timezone);
        $this->assertSame('Africa/Cairo', $other->fresh()->preferred_timezone);
        $this->assertEquals($original, $booking->fresh()->only(array_keys($original)));
        $this->get(route('student.profile'))->assertOk()->assertSee('America/New_York');
        $this->patch(route('student.profile.timezone'), ['timezone' => 'Invalid/Zone'])->assertSessionHasErrors('timezone');
        $this->assertSame('America/New_York', $student->fresh()->preferred_timezone);
        $this->assertEquals($original, $booking->fresh()->only(array_keys($original)));
    }

    private function document(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($document);
    }

    private function signIn(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String(), 'student_display_timezone' => 'Africa/Cairo']);
    }

    private function page(string $name, string $url): TestResponse
    {
        Livewire::flushState();
        $response = $this->get($url)->assertOk();
        $path = getenv('STUDENT_REFINEMENT_EVIDENCE_PATH');
        if (is_string($path) && $path !== '') {
            $base = realpath(storage_path(str_contains($path, 'student-portal-phase03-closure') ? 'app/private/student-portal-phase03-closure' : (str_contains($path, 'student-portal-phase03-evidence') ? 'app/private/student-portal-phase03-evidence' : (str_contains($path, 'student-portal-phase02-evidence') ? 'app/private/student-portal-phase02-evidence' : 'app/private/student-portal-phase01b-evidence'))));
            $resolved = realpath($path);
            if (! $base || ! $resolved || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Evidence must stay in the authorized private run directory.');
            }
            $html = preg_replace('/(data-csrf=")[^"]*(")/i', '$1REDACTED$2', $response->getContent());
            file_put_contents($resolved.'/'.$name.'.html', preg_replace('/(<(?:input|meta)\b[^>]*\bname="(?:_token|csrf-token)"[^>]*?\b(?:value|content)=")[^"]*(")/i', '$1REDACTED$2', $html));
            file_put_contents($resolved.'/responses.jsonl', json_encode(['file' => $name.'.html', 'url' => $url, 'status' => $response->getStatusCode(), 'fixture' => 'Synthetic Amina; frozen 2026-10-26 09:00 UTC; transactional rollback', 'method' => 'Laravel authenticated request test']).PHP_EOL, FILE_APPEND);
        }

        return $response;
    }
}
