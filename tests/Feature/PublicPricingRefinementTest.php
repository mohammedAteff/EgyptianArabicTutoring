<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\WebsiteCopy;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicPricingRefinementTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([0, '$280', '$255', '8 Private Sessions · 16 Learning Hours', '75 calendar days', 'A structured option for learners who want consistent guidance and regular opportunities to develop their Arabic.'])]
    #[TestWith([1, '$390', '$365', '12 Private Sessions · 24 Learning Hours', '100 calendar days', 'For learners who want more time for guided study, conversation, revision, and continued development.'])]
    public function test_track_separates_standard_price_conditional_balance_and_combined_expenditure(int $index, string $standard, string $remaining, string $hours, string $validity, string $description): void
    {
        $response = $this->get('/pricing');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $track = $xpath->query('//article[@class="pricing-track"]')->item($index);
        $credit = $xpath->query('.//div[@class="pricing-track-credit"]', $track)->item(0);
        $this->assertSame($description, $xpath->query('.//header/p[@class="pricing-track-summary"]', $track)->item(0)->textContent);
        $this->assertSame(1, substr_count($track->textContent, $description));
        $this->assertSame($standard, $xpath->query('.//p[@class="pricing-amount"]', $track)->item(0)->textContent);
        $this->assertStringContainsString($hours, $track->textContent);
        $this->assertStringContainsString($validity.' from the first payment, including a partial payment.', $track->textContent);
        $facts = $xpath->query('.//div[@class="pricing-track-facts"]', $track)->item(0);
        $this->assertStringContainsString('2 hours per session', $facts->textContent);
        $this->assertSame(0, $xpath->query('ancestor::details', $facts)->length);
        $this->assertStringContainsString('Standard package price', $xpath->query('.//div[@class="pricing-track-price"]', $track)->item(0)->textContent);
        $this->assertSame(0, $xpath->query('.//p[@class="pricing-amount"]', $credit)->length);
        $this->assertSame($remaining, $xpath->query('.//dd', $credit)->item(1)->textContent);
        $this->assertSame('Package balance after eligible credit', $xpath->query('.//dt', $credit)->item(1)->textContent);
        $conditions = $xpath->query('//*[@id="'.$track->getAttribute('aria-describedby').'"]')->item(0);
        $this->assertStringContainsString('within 48 hours after completing your diagnostic', $conditions->textContent);
        $this->assertSame(0, $xpath->query('ancestor::details', $conditions)->length);
        $this->assertSame('$25 paid for the diagnostic + '.$remaining.' remaining = '.$standard.' total when eligible.', $xpath->query('.//p[@class="pricing-total"]', $credit)->item(0)->textContent);
        $this->assertStringContainsString('If you purchase this coaching track within 48 hours after completing your diagnostic, the $25 already paid for the diagnostic is credited toward the standard package price.', $credit->textContent);
        $this->assertSame(0, $xpath->query('.//details', $credit)->length);
        $this->assertStringEndsWith('/booking', $xpath->query('.//a', $track)->item(0)->getAttribute('href'));
    }

    public function test_validity_disclosure_matches_first_payment_rule_without_the_stale_full_payment_statement(): void
    {
        $this->get('/pricing')
            ->assertSeeText('Package validity begins from the first payment, including a partial payment.')
            ->assertDontSeeText('Package validity begins from the actual date and time your full package payment is received.')
            ->assertSeeText('Payments are arranged manually and confirmed personally by Abdallah.')
            ->assertSeeText('Available to eligible returning students by arrangement.')
            ->assertSeeText('Available to eligible advanced learners by arrangement.');
    }

    public function test_published_copy_visibility_rich_text_and_additional_content_survive_the_pricing_layout(): void
    {
        WebsiteCopy::factory()->create(['page' => 'pricing', 'locale' => 'en', 'published_content' => [
            'pricing__0__title' => 'Published pricing introduction',
            'pricing__1__blocks__4__text' => '<p>Published <strong>diagnostic goals</strong>.</p>',
            'tracks__0__intro__0' => 'Published coaching-track description.',
            '_sections__pricing__3__visible' => false,
            '_sections__pricing__4__extra' => '<p>Additional approved lesson information.</p>',
        ]]);

        $this->get('/pricing')
            ->assertSeeText('Published pricing introduction')
            ->assertSee('<strong>diagnostic goals</strong>', false)
            ->assertSeeText('Published coaching-track description.')
            ->assertSeeText('Additional approved lesson information.')
            ->assertSeeText('Annotated lesson materials and tailored practice exercises')
            ->assertDontSee('class="pricing-support-band"', false)
            ->assertDontSeeText('Everything You Need to Keep Learning');
    }

    public function test_mobile_diagnostic_keeps_cms_explanations_and_material_offer_details(): void
    {
        WebsiteCopy::factory()->create(['page' => 'pricing', 'locale' => 'en', 'published_content' => [
            'pricing__1__blocks__4__text' => '<p>Published <strong>diagnostic goals</strong>.</p>',
            'pricing__1__blocks__7__text' => 'Published written roadmap prepared after the diagnostic.',
        ]]);

        $response = $this->get('/pricing');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $diagnostic = $xpath->query('//div[@class="pricing-diagnostic"]')->item(0);
        $disclosure = $xpath->query('.//details[contains(@class,"pricing-diagnostic-process")]', $diagnostic)->item(0);
        $this->assertSame('Explore the diagnostic process', $xpath->query('./summary', $disclosure)->item(0)->textContent);
        $this->assertSame(4, $xpath->query('.//li', $disclosure)->length);
        $this->assertSame('diagnostic goals', $xpath->query('.//strong', $disclosure)->item(0)->textContent);
        $this->assertStringContainsString('Published written roadmap prepared after the diagnostic.', $disclosure->textContent);
        $this->assertSame(4, $xpath->query('.//ul[@class="pricing-diagnostic-outcomes"]/li', $diagnostic)->length);
        $this->assertStringContainsString('Your written roadmap is prepared after the diagnostic.', $diagnostic->textContent);
        $this->assertSame(0, $xpath->query('.//details//div[@class="pricing-diagnostic-price"]', $diagnostic)->length);
        $this->assertStringContainsString('$25 · 60 minutes', $xpath->query('.//div[@class="pricing-diagnostic-offering"]', $diagnostic)->item(0)->textContent);
        $this->assertStringContainsString('consultation and assessment, not a regular teaching lesson', $xpath->query('.//div[@class="pricing-note"]', $diagnostic)->item(0)->textContent);
        $this->assertStringEndsWith('/booking', $xpath->query('.//a[@data-primary-cta="diagnostic"]', $diagnostic)->item(0)->getAttribute('href'));
    }

    public function test_shared_benefits_appear_once_below_the_comparison_and_priority_stays_with_fluency(): void
    {
        $response = $this->get('/pricing');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $tracks = $xpath->query('//article[@class="pricing-track"]');
        $this->assertSame(1, substr_count($response->getContent(), 'Annotated lesson materials and tailored practice exercises'));
        $this->assertSame(5, $xpath->query('//ul[contains(@class,"pricing-shared-benefits")]/li')->length);
        $this->assertStringNotContainsString('Priority scheduling', $tracks->item(0)->textContent);
        $this->assertStringContainsString('Priority scheduling, subject to availability', $tracks->item(1)->textContent);
        $this->assertStringContainsString('Whether you\'re learning the foundations', $xpath->query('.//details', $tracks->item(0))->item(0)->textContent);
        $this->assertStringContainsString('Twelve personalized, two-hour private lessons', $xpath->query('.//details', $tracks->item(1))->item(0)->textContent);
    }

    public function test_cms_package_specific_benefits_are_not_lost_when_shared_benefits_are_grouped(): void
    {
        WebsiteCopy::factory()->create(['page' => 'pricing', 'locale' => 'en', 'published_content' => [
            'tracks__0__benefits__2' => 'Approved Foundation-specific lesson materials',
        ]]);

        $response = $this->get('/pricing');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $tracks = $xpath->query('//article[@class="pricing-track"]');
        $this->assertStringContainsString('Approved Foundation-specific lesson materials', $tracks->item(0)->textContent);
        $this->assertStringContainsString('Annotated lesson materials and tailored practice exercises', $tracks->item(1)->textContent);
        $this->assertSame(4, $xpath->query('//ul[contains(@class,"pricing-shared-benefits")]/li')->length);
    }

    #[TestWith([true, true])]
    #[TestWith([true, false])]
    #[TestWith([false, true])]
    #[TestWith([false, false])]
    public function test_policy_sections_preserve_visible_section_ids_without_empty_wrappers(bool $showArrangements, bool $showRefunds): void
    {
        WebsiteCopy::factory()->create(['page' => 'pricing', 'locale' => 'en', 'published_content' => [
            '_sections__pricing__5__visible' => $showArrangements,
            '_sections__pricing__6__visible' => $showRefunds,
        ]]);

        $response = $this->get('/pricing');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame($showArrangements ? 1 : 0, $xpath->query('//div[@class="pricing-policy-band"]')->length);
        $this->assertSame($showArrangements ? 1 : 0, $xpath->query('//div[@class="pricing-policy-band"]/section[@id="pricing-section-6"]')->length);
        $this->assertSame($showRefunds ? 1 : 0, $xpath->query('//div[@class="pricing-refund-band"]')->length);
        $this->assertSame($showRefunds ? 1 : 0, $xpath->query('//div[@class="pricing-refund-band"]/section[@id="pricing-section-7"]')->length);
        $this->assertSame(1, $xpath->query('//div[@class="pricing-page"]/section[@id="pricing-section-8"]')->length);
        $this->assertSame(1, $xpath->query('//div[@class="pricing-support-band"]/section[@id="pricing-section-4"]')->length);
    }
}
