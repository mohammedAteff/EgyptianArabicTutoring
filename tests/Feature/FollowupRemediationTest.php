<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Services\EmailQualityService;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use App\Domains\Timezone\Services\TimezoneService;
use Database\Factories\AdministratorFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FollowupRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_and_icon_only_floating_contact_keep_separate_configuration(): void
    {
        $footer = SocialLink::create(['platform' => 'whatsapp', 'url_or_phone' => '+201011111111', 'label' => 'WhatsApp', 'default_message' => 'Footer message', 'enabled' => true]);
        Setting::set('whatsapp_public', false);
        $disabled = $this->get('/')->assertSee($footer->getFormattedUrl());
        $this->assertSame(0, $this->xpath($disabled->getContent())->query('//a[@data-whatsapp-cta]')->length);
        Setting::set('whatsapp_public', true);
        Setting::set('whatsapp_url', 'https://wa.me/201022222222');
        Setting::set('whatsapp_label', ['fr' => 'Parler avec Abdallah']);
        Setting::set('whatsapp_message', ['fr' => 'Bonjour']);

        $page = $this->get('/fr')->assertOk()->assertSee($footer->getFormattedUrl());
        $links = $this->xpath($page->getContent())->query('//a[@data-whatsapp-cta]');
        $this->assertCount(1, $links);
        $link = $links->item(0);
        $this->assertSame('', trim($link->textContent));
        $this->assertSame('Parler avec Abdallah', $link->getAttribute('aria-label'));
        $this->assertSame('https://wa.me/201022222222?text=Bonjour', $link->getAttribute('href'));
        $this->assertStringContainsString('fixed ', $link->getAttribute('class'));
        $this->assertStringContainsString('rounded-full', $link->getAttribute('class'));
        $this->assertSame(1, $link->getElementsByTagName('svg')->length);
        $this->assertSame('+201011111111', $footer->fresh()->url_or_phone);
    }

    #[TestWith([true, false, false])]
    #[TestWith([false, true, true])]
    public function test_portal_visibility_uses_its_own_switch(bool $public, bool $portal, bool $visible): void
    {
        Setting::set('whatsapp_public', $public);
        Setting::set('whatsapp_portal', $portal);
        Setting::set('whatsapp_url', 'https://wa.me/201022222222');
        $student = Student::factory()->verified()->create();

        $page = $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])->get(route('student.dashboard'))->assertOk();

        $this->assertSame($visible ? 1 : 0, $this->xpath($page->getContent())->query('//a[@data-whatsapp-cta]')->length);
    }

    public function test_maintenance_hides_the_floating_contact(): void
    {
        Setting::set('whatsapp_public', true);
        Setting::set('whatsapp_url', 'https://wa.me/201022222222');
        Setting::set('maintenance_mode', true);
        Setting::set('maintenance_message', 'Returning tomorrow');

        $this->get('/')->assertStatus(503)->assertSee('Returning tomorrow')->assertDontSee('data-whatsapp-cta', false);
    }

    public function test_whatsapp_placements_survive_ingestion_as_distinct_single_events(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 Followup Browser');
        $page = $this->get('/');
        $this->withCredentials()->withCookies(['_va_visitor' => $page->getCookie('_va_visitor')->getValue(), '_va_session' => $page->getCookie('_va_session')->getValue()]);
        $events = [];
        foreach (['floating_cta', 'footer_social'] as $placement) {
            $events[] = ['event_uuid' => (string) Str::uuid(), 'event_name' => 'whatsapp_clicked', 'page' => '/', 'metadata' => ['platform' => 'whatsapp', 'placement' => $placement, 'context' => 'public', 'language' => 'en', 'target_url' => 'https://wa.me/201022222222']];
        }

        $this->postJson(route('analytics.track'), ['events' => $events])->assertOk();

        $stored = AnalyticsEvent::where('event_name', 'whatsapp_clicked')->get();
        $this->assertCount(2, $stored);
        $this->assertSame(['floating_cta', 'footer_social'], $stored->pluck('metadata.placement')->all());
    }

    public function test_cashier_renders_multiple_manual_refunds_and_export_parity_without_duplicate_rows(): void
    {
        $this->freezeTime();
        $admin = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Synthetic refund fixture', 2, '100.00', '0.00', 'USD', null, 'followup-package', entitlementCode: 'one_hour');
        $this->actingAs($admin, 'web')->post(route('admin.students.payments.store', ['student' => $student->id, 'package' => $package->id]), ['amount_paid' => '120.00', 'payment_method' => 'PayPal - Manual', 'transaction_reference' => 'SYNTHETIC-NO-TRANSFER', 'payment_idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $payment = PaymentRecord::sole();
        $refundUrl = route('admin.students.refunds.store', ['student' => $student->id, 'payment' => $payment->id]);
        $this->post($refundUrl, ['amount_refunded' => '20.00', 'reason' => 'Synthetic excess payment', 'refund_idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $firstRefund = PaymentRefund::sole();

        $page = $this->get(route('admin.billing.cashier', ['student_id' => $student->id]))->assertOk()->assertSee('Partially refunded')->assertSee('-$20.00 USD')->assertSee('Synthetic excess payment')->assertSee('up to $100.00')->assertSee('SYNTHETIC-NO-TRANSFER');
        $this->assertSame(1, $this->xpath($page->getContent())->query('//div[@data-refund-id="'.$firstRefund->id.'"]')->length);
        $page->assertSee(app(TimezoneDisplayService::class)->administratorDateTime($firstRefund->refunded_at));
        $summary = $ledger->summary($package);
        $this->assertSame('100.00', $summary['net_paid']);
        $this->assertSame('0.00', $summary['balance_due']);
        $this->assertSame('0.00', $summary['overpaid']);
        $this->assertSame(2, $summary['remaining_credits']);

        $this->post($refundUrl, ['amount_refunded' => '100.00', 'reason' => 'Synthetic final refund', 'refund_idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $page = $this->get(route('admin.billing.cashier', ['student_id' => $student->id]))->assertOk()->assertSee('Fully refunded')->assertSee('$120.00 USD')->assertSee('-$100.00 USD')->assertSee('-$20.00 USD');
        $paymentNode = $this->xpath($page->getContent())->query('//div[@data-payment-id="'.$payment->id.'"]')->item(0);
        $this->assertSame(0, $paymentNode->getElementsByTagName('button')->length);
        $this->assertSame(2, $this->xpath($page->getContent())->query('//div[@data-refund-id]')->length);
        $this->assertSame('100.00', $ledger->summary($package)['balance_due']);
        $this->assertSame('0.00', $ledger->summary($package)['net_paid']);
        $csv = $this->get(route('admin.billing.export', ['format' => 'csv']))->assertOk()->streamedContent();
        foreach (PaymentRefund::all() as $refund) {
            $this->assertStringContainsString('REF-'.$refund->id.',PAY-'.$payment->id, $csv);
            $this->assertStringContainsString('-'.(float) $refund->amount_refunded, $csv);
            $this->assertStringContainsString($refund->refunded_at->copy()->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->format('Y-m-d H:i'), $csv);
        }
        $this->assertDatabaseCount('payment_records', 1);
        $this->assertDatabaseCount('payment_refunds', 2);
    }

    #[TestWith(['not-an-email', 'Please enter a valid email address.'])]
    #[TestWith(['learner@localhost', 'Please enter a valid email address.'])]
    #[TestWith(['learner@mailinator.com', 'Please use a permanent email address.'])]
    #[TestWith(['learner@unroutable.example', 'Please enter a valid email address.'])]
    public function test_resource_email_errors_are_customer_safe_even_without_cookies(string $email, string $message): void
    {
        $resource = $this->resource();
        $this->partialMock(EmailQualityService::class)->shouldReceive('dnsRecords')->andReturn([]);

        $this->postJson(route('resources.request', $resource->slug), ['name' => 'Synthetic learner', 'email' => $email])->assertUnprocessable()->assertJsonPath('errors.email.0', $message)->assertDontSee('security context');

        $this->assertDatabaseCount('resource_requests', 0);
    }

    #[TestWith(['none'])]
    #[TestWith(['visitor'])]
    #[TestWith(['session'])]
    public function test_resource_missing_cookies_return_403_with_a_safe_retry_message(string $presentCookie): void
    {
        $resource = $this->resource();
        $this->partialMock(EmailQualityService::class)->shouldReceive('dnsRecords')->andReturn([['type' => 'MX', 'target' => 'mail.routable.example']]);
        Log::spy();
        if ($presentCookie !== 'none') {
            $this->withCredentials()->withCookie($presentCookie === 'visitor' ? '_va_visitor' : '_va_session', (string) Str::uuid());
        }

        $response = $this->postJson(route('resources.request', $resource->slug), ['name' => 'Synthetic learner', 'email' => 'learner@routable.example'])->assertForbidden()->assertJsonPath('message', "We couldn't process your request. Please refresh the page and try again.");

        foreach (['security context', 'visitor token', 'session token', 'cookie authority', 'middleware'] as $term) {
            $response->assertDontSee($term);
        }
        Log::shouldHaveReceived('notice')->once();
        $this->assertDatabaseCount('resource_requests', 0);
    }

    #[TestWith(['administrator'])]
    #[TestWith(['internal_connection'])]
    #[TestWith(['synthetic'])]
    public function test_analytics_excluded_visitors_can_get_session_bound_resource_access_without_telemetry(string $exclusion): void
    {
        Mail::fake();
        $resource = $this->resource();
        $this->partialMock(EmailQualityService::class)->shouldReceive('dnsRecords')->andReturn([['type' => 'MX', 'target' => 'mail.routable.example']]);
        $this->withHeader('User-Agent', 'Mozilla/5.0 Followup Browser');
        if ($exclusion === 'administrator') {
            $this->actingAs(AdministratorFactory::new()->create(), 'web');
        } elseif ($exclusion === 'internal_connection') {
            Setting::set('analytics.internal_hashes', [hash_hmac('sha256', '127.0.0.1', (string) config('app.key'))]);
        } else {
            $this->withHeader('X-Analytics-Synthetic', '1');
        }
        $page = $this->get(route('resources.show', $resource->slug))->assertOk()->assertCookie('_va_visitor')->assertCookie('_va_session');
        $this->withCredentials()->withCookies(['_va_visitor' => $page->getCookie('_va_visitor')->getValue(), '_va_session' => $page->getCookie('_va_session')->getValue()]);

        $issued = $this->postJson(route('resources.request', $resource->slug), ['name' => 'Synthetic learner', 'email' => 'learner@routable.example'])->assertOk()->assertJsonPath('requires_pin', false);

        $this->assertDatabaseCount('resource_requests', 1);
        $this->assertDatabaseCount('visitors', 0);
        $this->assertDatabaseCount('visitor_sessions', 0);
        $this->assertDatabaseCount('analytics_events', 0);
        $this->withCookie(config('session.cookie'), $issued->getCookie(config('session.cookie'))->getValue());
        $this->get($issued->json('download_url'))->assertRedirect('https://example.org/followup-guide.pdf');
        $this->assertDatabaseCount('resource_downloads', 1);
        Mail::assertNothingOutgoing();
    }

    public function test_settings_save_all_four_groups_inside_the_existing_container_without_changing_footer(): void
    {
        $admin = AdministratorFactory::new()->create();
        $footer = SocialLink::create(['platform' => 'whatsapp', 'url_or_phone' => '+201011111111', 'label' => 'WhatsApp', 'default_message' => 'Footer message', 'enabled' => true]);
        $originalFooter = $footer->fresh()->toArray();

        $this->actingAs($admin, 'web')->post(route('admin.settings.operations'), ['maintenance_message' => 'Back tomorrow', 'whatsapp_public' => 1, 'whatsapp_portal' => 0, 'whatsapp_url' => 'https://wa.me/201022222222', 'whatsapp_label' => ['en' => 'Chat now', 'fr' => 'Discuter', 'de' => 'Kontakt'], 'whatsapp_message' => ['en' => 'Hello', 'fr' => 'Bonjour', 'de' => 'Hallo'], 'goals' => ['whatsapp_clicked'], 'exclude_connection' => 1])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($originalFooter, $footer->fresh()->toArray());
        $this->assertSame('Back tomorrow', Setting::get('maintenance_message'));
        $this->assertSame(['whatsapp_clicked'], Setting::get('analytics.goals'));
        $this->assertSame([hash_hmac('sha256', '127.0.0.1', (string) config('app.key'))], Setting::get('analytics.internal_hashes'));
        $page = $this->get(route('admin.settings.index'))->assertOk()->assertSee('Chat now')->assertSee('Back tomorrow');
        $xpath = $this->xpath($page->getContent());
        foreach (['maintenance-message-heading', 'floating-whatsapp-heading', 'conversion-goals-heading', 'internal-traffic-heading'] as $heading) {
            $this->assertSame(1, $xpath->query('//div[contains(@class,"max-w-4xl")]/form/section[@aria-labelledby="'.$heading.'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//input[@name="whatsapp_public" and @type="checkbox" and @checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="whatsapp_portal" and @type="checkbox" and @checked]')->length);
        $page->assertDontSee('url_or_phone');
    }

    private function resource(): Resource
    {
        $category = ResourceCategory::create(['name' => 'Synthetic guides', 'slug' => 'synthetic-guides', 'active' => true]);

        return Resource::create(['title' => 'Synthetic access guide', 'slug' => 'followup-access-guide', 'category_id' => $category->id, 'status' => 'published', 'published_at' => now()->subDay(), 'is_gated' => true, 'external_url' => 'https://example.org/followup-guide.pdf']);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }
}
