<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\CMS\Services\PublicContentImporter;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicCopyRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_imports_preserve_social_records_and_the_footer_respects_enabled_flags(): void
    {
        $enabled = SocialLink::create(['platform' => 'youtube', 'url_or_phone' => 'https://example.test/engineering-enabled', 'label' => 'Engineering enabled social', 'enabled' => true, 'sort_order' => 1]);
        $disabled = SocialLink::create(['platform' => 'instagram', 'url_or_phone' => 'https://example.test/engineering-disabled', 'label' => 'Engineering disabled social', 'enabled' => false, 'sort_order' => 2]);
        $before = [$enabled->fresh()->getRawOriginal(), $disabled->fresh()->getRawOriginal()];

        app(PublicContentImporter::class)->import();
        config(['public_content.version' => 'engineering-remediation-import-2']);
        app(PublicContentImporter::class)->import();

        $this->assertSame($before, [$enabled->fresh()->getRawOriginal(), $disabled->fresh()->getRawOriginal()]);
        $this->assertDatabaseCount('social_links', 2);
        $this->get('/')->assertSeeText('Engineering enabled social')->assertSee('href="https://example.test/engineering-enabled"', false)
            ->assertDontSeeText('Engineering disabled social')->assertDontSee('https://example.test/engineering-disabled', false)
            ->assertSeeText('Arabic with Abdallah')->assertSeeText('Blogs');
    }

    #[TestWith(['1', '0'])]
    #[TestWith(['0', '1'])]
    #[TestWith(['1', '1'])]
    #[TestWith(['0', '0'])]
    public function test_copy_import_preserves_independent_whatsapp_configuration_and_display(string $public, string $portal): void
    {
        $values = ['whatsapp_public' => $public, 'whatsapp_portal' => $portal, 'whatsapp_url' => 'https://wa.me/201234567890',
            'whatsapp_label' => ['en' => 'Engineering WhatsApp'], 'whatsapp_message' => ['en' => 'Engineering message']];
        foreach ($values as $key => $value) {
            Setting::set($key, $value, 'operations');
        }
        $before = Setting::query()->where('key', 'like', 'whatsapp_%')->orderBy('key')->get()->map(fn (Setting $setting): array => $setting->getRawOriginal())->all();

        app(PublicContentImporter::class)->import();

        $this->assertSame($before, Setting::query()->where('key', 'like', 'whatsapp_%')->orderBy('key')->get()->map(fn (Setting $setting): array => $setting->getRawOriginal())->all());
        $publicResponse = $this->get('/');
        $portalView = $this->blade('<x-whatsapp-cta context="portal" />');
        $this->assertSame($public === '1' ? 1 : 0, $this->xpath($publicResponse->getContent())->query('//a[@data-whatsapp-cta and @data-context="public"]')->length);
        $this->assertSame($portal === '1' ? 1 : 0, $this->xpath((string) $portalView)->query('//a[@data-whatsapp-cta and @data-context="portal"]')->length);
        if ($public === '1') {
            $publicResponse->assertSee('href="https://wa.me/201234567890?text=Engineering%20message"', false)->assertSee('aria-label="Engineering WhatsApp"', false);
        }
    }

    public function test_copy_import_does_not_create_missing_whatsapp_configuration(): void
    {
        app(PublicContentImporter::class)->import();

        foreach (['whatsapp_public', 'whatsapp_portal', 'whatsapp_url'] as $key) {
            $this->assertDatabaseMissing('settings', ['key' => $key]);
        }
        $response = $this->get('/');
        $this->assertSame(0, $this->xpath($response->getContent())->query('//a[@data-whatsapp-cta]')->length);
    }

    public function test_home_retains_approved_copy_new_section_ids_and_legacy_fragments_with_analytics(): void
    {
        $response = $this->get('/');

        $response->assertSeeText('Learn Arabic with a plan built around you.')->assertSeeText('More About Abdallah')->assertSeeText('Book Your Diagnostic');
        $xpath = $this->xpath($response->getContent());
        foreach (['hero' => 1, 'curriculum' => 3, 'pricing' => 4, 'tutor-bio' => 7] as $legacy => $number) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$legacy.'"]')->length);
            $section = $xpath->query('//section[@id="home-section-'.$number.'"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $section);
            $this->assertSame($legacy, $section->getAttribute('data-section-id'));
        }
        for ($number = 1; $number <= 9; $number++) {
            $this->assertSame(1, $xpath->query('//*[@id="home-section-'.$number.'"]')->length);
        }
    }

    #[TestWith(['http://example.test/engineering-tutor.jpg'])]
    #[TestWith(['https://example.test/engineering-tutor.webp?width=400&height=400'])]
    #[TestWith(['HTTPS://example.test/engineering-tutor.png'])]
    public function test_public_about_and_admin_preview_render_the_same_configured_external_portrait(string $source): void
    {
        Http::preventStrayRequests();
        Setting::set('about_image_path', $source, 'about');
        $setting = Setting::query()->where('key', 'about_image_path')->sole();
        $before = $setting->getRawOriginal();
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);

        $public = $this->get('/about');
        $preview = $this->actingAs($admin)->get(route('admin.settings.index'));

        $public->assertSeeText('Book Your Diagnostic');
        $this->assertSame($source, $this->xpath($public->getContent())->evaluate('string(//img[@alt="Abdallah"]/@src)'));
        $this->assertSame($source, $this->xpath($preview->getContent())->evaluate('string(//img[@id="about_image_preview"]/@src)'));
        $this->assertSame($before, $setting->fresh()->getRawOriginal());
        Http::assertNothingSent();
    }

    public function test_public_about_and_admin_preview_keep_existing_public_media_portraits(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('engineering-tutor.png');
        Storage::disk('public')->put('media/engineering tutor.png', file_get_contents($image->getPathname()));
        Setting::set('about_image_path', 'media/engineering tutor.png', 'about');
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);

        $public = $this->get('/about');
        $preview = $this->actingAs($admin)->get(route('admin.settings.index'));

        $expected = Storage::disk('public')->url('media/engineering tutor.png');
        $this->assertSame($expected, $this->xpath($public->getContent())->evaluate('string(//img[@alt="Abdallah"]/@src)'));
        $this->assertSame($expected, $this->xpath($preview->getContent())->evaluate('string(//img[@id="about_image_preview"]/@src)'));
        Storage::disk('public')->assertExists('media/engineering tutor.png');
        $this->assertSame('media/engineering tutor.png', Setting::get('about_image_path'));
    }

    #[DataProvider('invalidPortraitSources')]
    public function test_public_about_and_admin_preview_omit_unsafe_invalid_or_missing_portraits(string $source): void
    {
        Storage::fake('public');
        Setting::set('about_image_path', $source, 'about');
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);

        $public = $this->get('/about');
        $preview = $this->actingAs($admin)->get(route('admin.settings.index'));

        $public->assertSeeText('Book Your Diagnostic');
        $this->assertSame(0, $this->xpath($public->getContent())->query('//img[@alt="Abdallah"]')->length);
        $this->assertSame('', $this->xpath($preview->getContent())->evaluate('string(//img[@id="about_image_preview"]/@src)'));
        $this->assertSame($source, Setting::get('about_image_path'));
    }

    /** @return array<string, array{string}> */
    public static function invalidPortraitSources(): array
    {
        return [
            'empty' => [''], 'javascript' => ['javascript:alert(1)'], 'data' => ['data:image/svg+xml,<svg></svg>'],
            'ftp' => ['ftp://example.test/tutor.png'], 'protocol relative' => ['//example.test/tutor.png'],
            'credentials' => ['https://user:password@example.test/tutor.png'], 'no host' => ['https://'],
            'invalid host' => ['https://invalid host/tutor.png'], 'missing file' => ['media/missing.png'],
            'parent traversal' => ['../tutor.png'], 'nested traversal' => ['media/../tutor.png'],
            'encoded traversal' => ['media/%2e%2e/tutor.png'], 'backslash' => ['media\\tutor.png'],
            'control character' => ["https://example.test/tutor.png\n"], 'encoded control' => ['media/tutor%00.png'],
            'attribute injection' => ['https://example.test/tutor.png" onerror="alert(1)'],
        ];
    }

    public function test_empty_resource_library_uses_its_actual_empty_state_without_a_workbook_claim(): void
    {
        $this->get('/resources')->assertSeeText('Learning materials are being prepared.')
            ->assertSeeText("There aren't any published resources in the library at the moment.")
            ->assertDontSeeText('awaiting its confirmed file')->assertDontSeeText('Explore the Workbook');
    }

    public function test_published_alphabet_resource_renders_from_data_and_keeps_its_name_email_access_gate(): void
    {
        $resource = ResourceFactory::new()->create(['title' => 'Arabic Alphabet', 'slug' => 'engineering-alphabet',
            'external_url' => 'https://example.test/engineering-alphabet', 'is_gated' => true]);
        $before = $resource->fresh()->getRawOriginal();

        $this->get('/resources')->assertSeeText('Arabic Alphabet')->assertDontSeeText('Learning materials are being prepared.')
            ->assertDontSeeText('awaiting its confirmed file')->assertSee(route('resources.show', $resource->slug));
        $this->get(route('resources.show', $resource->slug))->assertSeeText('Get Free Access')->assertSee('name="name"', false)->assertSee('name="email"', false);
        $this->get(route('resources.download', $resource->slug))->assertRedirect(route('resources.show', $resource->slug));

        $this->assertSame($before, $resource->fresh()->getRawOriginal());
        $this->assertDatabaseCount('resource_requests', 0);
        $this->assertDatabaseCount('resource_downloads', 0);
    }

    public function test_imported_resource_faq_keeps_planned_video_wording_without_blanket_workbook_unavailability(): void
    {
        app(PublicContentImporter::class)->import();

        $this->get('/faq')->assertSeeText('The accompanying video series is planned.')
            ->assertDontSeeText('awaiting its confirmed file')->assertDontSeeText('not available to download yet');
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html !== '' ? $html : '<html></html>', LIBXML_NONET);

        return new DOMXPath($document);
    }
}
