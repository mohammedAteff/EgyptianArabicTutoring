<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Resources\Services\EmailQualityService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\FinancialStatementService;
use App\Domains\Students\Services\StudentEmailService;
use App\Mail\StudentSecondaryEmailVerification;
use App\Mail\StudentSecurityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PortalLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(Student $student, string $locale): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'public_locale' => $locale]);
        $this->withHeader('X-Analytics-Synthetic', '1');
    }

    #[TestWith(['en', 'available lesson', 'available lessons', 'In Progress'])]
    #[TestWith(['fr', 'leçon disponible', 'leçons disponibles', 'En cours'])]
    #[TestWith(['de', 'verfügbare Lektion', 'verfügbare Lektionen', 'In Bearbeitung'])]
    public function test_course_counts_and_started_states_use_the_interface_language(string $locale, string $singular, string $plural, string $started): void
    {
        Http::preventStrayRequests();
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        app(LmsAccessOperations::class)->grant(Administrator::factory()->create(), $student, $course, [], 'counts-'.Str::uuid());
        $this->signIn($student, $locale);
        $previous = 0;
        foreach ([0, 1, 2, 15] as $count) {
            for ($index = $previous; $index < $count; $index++) {
                Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
            }
            $this->get(route('student.learning.courses.show', $course))->assertOk()->assertSeeText($count.' '.($count === 1 ? $singular : $plural));
            $previous = $count;
        }
        $lesson = Lesson::where('course_id', $course->id)->firstOrFail();
        $this->get(route('student.learning.lessons.show', [$course, $lesson]))->assertOk()->assertSeeText($started);
    }

    #[TestWith(['en', '1 point', '2 points', 'Up to 1 attempt for this version.'])]
    #[TestWith(['fr', '1 point', '2 points', '1 tentative maximum pour cette version.'])]
    #[TestWith(['de', '1 Punkt', '2 Punkte', 'Höchstens 1 Versuch für diese Version.'])]
    public function test_quiz_interface_pluralizes_and_keeps_teacher_authored_options(string $locale, string $one, string $two, string $limit): void
    {
        Http::preventStrayRequests();
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        app(LmsAccessOperations::class)->grant(Administrator::factory()->create(), $student, $course, [], 'locale-'.Str::uuid());
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => ['title' => 'Authored quiz · العربية', 'passing_score' => 80, 'attempt_limit' => 1, 'review_policy' => 'after_grading', 'questions' => [
            ['type' => 'choice', 'prompt' => 'Authored prompt', 'points' => 1, 'options' => ['Home', 'Notes', 'أهلاً'], 'answer' => 0],
            ['type' => 'matching', 'prompt' => 'Match the authored words', 'points' => 2, 'options' => ['Bookmarks', 'Homework'], 'answer' => ['المفضلة', 'واجب']],
        ]]]);
        $this->signIn($student, $locale);
        $this->post(route('student.evidence.quiz.begin', [$course, $lesson, $block]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $response = $this->get(route('student.evidence.show', [$course, $lesson, $block]))->assertOk()->assertSeeText($one)->assertSeeText($two)->assertSeeText($limit);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame('Home', trim($xpath->evaluate('string(//input[@type="radio" and @value="0"]/following-sibling::span)')));
        $this->assertSame('Bookmarks', trim($xpath->evaluate('string(//select[@name="answers[1][0]"]/preceding-sibling::span)')));
        $response->assertSeeText('أهلاً')->assertSeeText('المفضلة');
    }

    #[TestWith(['fr', 'Activité récente', 'Séance effectuée', 'Séance de cours nº'])]
    #[TestWith(['de', 'Letzte Aktivitäten', 'Sitzung durchgeführt', 'Unterrichtssitzung Nr.'])]
    public function test_recent_activity_localizes_system_text_while_homework_titles_remain_authored(string $locale, string $heading, string $delivered, string $reference): void
    {
        Http::preventStrayRequests();
        $this->travelTo('2026-11-09 12:00:00 UTC');
        $student = Student::factory()->verified()->create();
        Homework::factory()->create(['student_id' => $student->id, 'title' => 'Home', 'student_visible' => true]);
        $booking = Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed', 'completed_at' => now('UTC')->subDay()]);
        $this->signIn($student, $locale);
        $response = $this->get(route('student.home'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame('Home', trim($xpath->evaluate('string(//*[@data-home-action="homework"]/p[@dir="auto"])')));
        $this->get(route('student.learning.index'))->assertOk()->assertSeeText($heading)->assertSeeText($delivered)->assertSeeText($reference.' '.$booking->id)->assertDontSeeText('Session delivered');
    }

    #[TestWith(['fr', 'Enregistrer mes indisponibilités', 'Rejoindre la liste d’attente', 'Vacances / voyage'])]
    #[TestWith(['de', 'Abwesenheitszeiten speichern', 'Auf die Warteliste setzen', 'Urlaub / Reise'])]
    public function test_planning_buttons_and_system_reasons_are_localized_without_changing_option_values(string $locale, string $save, string $join, string $holiday): void
    {
        $student = Student::factory()->verified()->create();
        $this->signIn($student, $locale);
        $response = $this->get(route('student.scheduling.index'))->assertOk()->assertSeeText($save)->assertSeeText($join);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame($holiday, trim($xpath->evaluate('string(//select[@name="reason_code"]/option[@value="holiday"])')));
        $response->assertDontSeeText('Holiday / travel')->assertDontSeeText('Save Unavailable Dates');
    }

    #[TestWith(['en', 'Confirm your student authenticator setup', 'This link expires in ten minutes.'])]
    #[TestWith(['fr', 'Confirmez la configuration de votre application d’authentification', 'Ce lien expire dans dix minutes.'])]
    #[TestWith(['de', 'Bestätigen Sie die Einrichtung Ihrer Authentifizierungs-App', 'Dieser Link läuft nach zehn Minuten ab.'])]
    public function test_security_mail_retains_the_requested_language_when_rendered_in_another_locale(string $locale, string $subject, string $expiry): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        $student = Student::factory()->verified()->create();
        $this->signIn($student, $locale);
        $this->post(route('student.security.enroll'))->assertRedirect(route('student.security.show'));
        $captured = null;
        Mail::assertSent(StudentSecurityVerification::class, function (StudentSecurityVerification $mail) use ($student, $locale, &$captured): bool {
            $captured = $mail;

            return $mail->hasTo($student->email_normalized) && $mail->locale === $locale;
        });
        app()->setLocale($locale === 'de' ? 'fr' : 'de');
        $renderLocale = app()->getLocale();
        $captured->assertSeeInHtml($expiry)->assertSeeInHtml('lang="'.$locale.'"', false)->assertSeeInHtml($captured->verificationUrl);
        $this->assertSame($subject, $captured->subject);
        $this->assertSame($renderLocale, app()->getLocale());
        $this->assertFalse($student->fresh()->requiresTwoFactor());
    }

    #[TestWith(['en', 'Verify your secondary student email', 'Verify this email'])]
    #[TestWith(['fr', 'Vérifiez votre adresse e-mail secondaire', 'Vérifier cette adresse e-mail'])]
    #[TestWith(['de', 'Bestätigen Sie Ihre zusätzliche E-Mail-Adresse für das Lernportal', 'Diese E-Mail-Adresse bestätigen'])]
    public function test_secondary_email_verification_captures_its_locale_without_verifying_the_address(string $locale, string $subject, string $action): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        $this->partialMock(EmailQualityService::class)->shouldReceive('dnsRecords')->andReturn([['type' => 'MX', 'target' => 'mail.fixture.test']]);
        $student = Student::factory()->verified()->create();
        app()->setLocale($locale);
        app(StudentEmailService::class)->requestVerification($student, 'secondary@example.test');
        $captured = null;
        Mail::assertSent(StudentSecondaryEmailVerification::class, function (StudentSecondaryEmailVerification $mail) use ($locale, &$captured): bool {
            $captured = $mail;

            return $mail->hasTo('secondary@example.test') && $mail->locale === $locale;
        });
        app()->setLocale($locale === 'de' ? 'fr' : 'de');
        $captured->assertSeeInHtml($action)->assertSeeInHtml('lang="'.$locale.'"', false)->assertSeeInHtml($captured->verificationUrl);
        $this->assertSame($subject, $captured->subject);
        $this->assertDatabaseMissing('student_emails', ['student_id' => $student->id, 'email_normalized' => 'secondary@example.test']);
    }

    public function test_portal_locale_precedence_handles_malformed_input_and_explicit_english_reset(): void
    {
        Http::preventStrayRequests();
        $this->get('/student/login')->assertOk()->assertSee('<html lang="en"', false);
        $this->withCookie('public_locale', 'de')->get('/student/login')->assertOk()->assertSee('<html lang="de"', false);
        $this->withSession(['public_locale' => 'fr'])->get('/student/login?lang[]=de')->assertOk()->assertSee('<html lang="fr"', false)->assertSessionHas('public_locale', 'fr');
        $this->get('/student/login?lang=ar')->assertOk()->assertSee('<html lang="fr"', false)->assertSessionHas('public_locale', 'fr');
        $this->get('/student/login?lang=de')->assertOk()->assertSee('<html lang="de"', false)->assertCookie('public_locale', 'de');
        $this->get('/student/login?lang=en')->assertOk()->assertSee('<html lang="en"', false)->assertCookie('public_locale', 'en');
        $this->get('/student/login')->assertOk()->assertSee('<html lang="en"', false);
    }

    public function test_signout_removes_identity_but_retains_selected_interface_language(): void
    {
        $student = Student::factory()->verified()->create();
        $this->signIn($student, 'de');
        $this->post(route('student.logout'))->assertRedirect(route('student.login'))->assertSessionMissing('student_id')->assertSessionHas('public_locale', 'de');
        $this->get(route('student.login'))->assertOk()->assertSee('<html lang="de"', false);
        $this->assertGuest('student');
    }

    #[TestWith(['fr', 'Détails du forfait', 'Reçu de paiement', 'Type d’opération', 'Paiement enregistré'])]
    #[TestWith(['de', 'Paketinformationen', 'Zahlungsbeleg', 'Eintragsart', 'Erfasste Zahlung'])]
    public function test_populated_statements_receipts_and_exports_localize_system_labels_and_preserve_recorded_values(string $locale, string $heading, string $receiptTitle, string $recordHeader, string $paymentLabel): void
    {
        $this->travelTo('2026-11-09 12:00:00 UTC');
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Europe/Paris']);
        $package = StudentPackage::factory()->create(['student_id' => $student->id, 'package_name' => 'Home · العربية', 'original_price' => '125.50', 'final_price' => '125.50', 'currency' => 'USD']);
        $payment = PaymentRecord::factory()->create(['student_package_id' => $package->id, 'student_id' => $student->id, 'amount_paid' => '25.50', 'payment_method' => 'Home', 'transaction_reference' => 'REF-العربية-001']);
        PaymentRefund::factory()->create(['payment_record_id' => $payment->id, 'student_package_id' => $package->id, 'student_id' => $student->id, 'amount_refunded' => '5.00']);
        $before = [$package->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal()];
        $this->signIn($student, $locale);
        $this->get(route('student.statements.index'))->assertOk()->assertSeeText('125,50');
        $this->get(route('student.statements.show', $package))->assertOk()->assertSeeText($heading)->assertSeeText('125,50')->assertSeeText('Home · العربية')->assertSeeText('Home')->assertSeeText('Europe/Paris')->assertDontSeeText('Typed credit balance:');
        $this->get(route('student.receipts.show', [$package, $payment]))->assertOk()->assertSeeText($receiptTitle)->assertSeeText('25,50')->assertSeeText('REF-العربية-001')->assertHeader('Cache-Control', 'no-store, private');
        $csv = $this->get(route('student.statements.export', $package))->assertOk()->streamedContent();
        $lines = preg_split('/\r?\n/', ltrim($csv, "\xEF\xBB\xBF"));
        $this->assertSame($recordHeader, str_getcsv($lines[0])[0]);
        $this->assertSame($paymentLabel, str_getcsv($lines[2])[0]);
        $this->assertSame('25.50', str_getcsv($lines[2])[3]);
        $this->assertSame('USD', str_getcsv($lines[2])[4]);
        $this->assertSame('Home', str_getcsv($lines[2])[5]);
        $this->assertSame('REF-العربية-001', str_getcsv($lines[2])[6]);
        $xlsx = $this->get(route('student.statements.export', ['package' => $package, 'format' => 'xlsx']))->assertOk();
        $exportPath = $xlsx->baseResponse->getFile()->getPathname();
        $workbook = IOFactory::load($exportPath);
        $this->assertSame($recordHeader, $workbook->getActiveSheet()->getCell('A1')->getValue());
        $this->assertSame('25.50', $workbook->getActiveSheet()->getCell('D3')->getValue());
        $workbook->disconnectWorksheets();
        unlink($exportPath);
        $staffRows = iterator_to_array(app(FinancialStatementService::class)->rows($package, false));
        $this->assertSame('Purchase', $staffRows[0][0]);
        $this->assertSame('Actual payment', $staffRows[1][0]);
        $this->assertSame($before, [$package->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal()]);
    }
}
