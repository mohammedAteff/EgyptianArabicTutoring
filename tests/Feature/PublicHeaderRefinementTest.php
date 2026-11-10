<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\WebsiteCopy;
use App\Domains\Games\Models\Game;
use Database\Factories\ResourceFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicHeaderRefinementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_language_switching_replaces_the_language_parameter_through_repeated_visits(): void
    {
        $path = '/?source=homepage&lang=en';

        foreach (['fr' => '/fr', 'de' => '/de', 'en' => '/'] as $locale => $expectedPath) {
            $response = $this->get($path);
            $document = new DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new DOMXPath($document);
            $link = $xpath->query('//header//a[@class="public-language-link"]//span[@aria-hidden="true"][text()="'.strtoupper($locale).'"]/..')->item(0);
            $this->assertNotNull($link);
            $path = $link->getAttribute('href');
            parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
            ksort($query);
            $this->assertSame($expectedPath, parse_url($path, PHP_URL_PATH) ?: '/');
            $this->assertSame(['lang' => $locale, 'source' => 'homepage'], $query);
            $this->assertSame(1, substr_count($path, 'lang='));
        }

        $this->get($path)->assertOk()->assertSessionHas('public_locale', 'en');
    }

    #[TestWith(['/'])]
    #[TestWith(['/pricing'])]
    #[TestWith(['/about'])]
    #[TestWith(['/booking'])]
    public function test_public_header_exposes_one_navigation_with_the_approved_logo_and_real_destinations(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk()
            ->assertDontSee('herd-ai-root', false)
            ->assertDontSee('/herd-studio/loader.js', false);
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//header//nav[@id="public-navigation"]')->length);
        $this->assertSame(1, $xpath->query('//header//img[@alt="Arabic with Abdallah"]')->length);
        foreach (['/', '/resources', '/games', '/pricing', '/about', '/faq', '/blog', '/student/login', '/booking'] as $destination) {
            $this->assertSame(1, $xpath->query('//nav[@id="public-navigation"]//a[@href="'.url($destination).'"]')->length, $destination);
        }
        $this->assertSame(1, $xpath->query('//header//a[@aria-current="page"]')->length);
        $this->assertSame(1, $xpath->query('//button[@aria-controls="public-navigation"]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="#main-content"]')->length);
        $this->assertSame(1, $xpath->query('//main[@id="main-content"]')->length);
    }

    public function test_diagnostic_layout_preserves_sanitized_published_rich_text(): void
    {
        WebsiteCopy::factory()->create(['page' => 'home', 'locale' => 'en', 'published_content' => [
            'home__3__blocks__4__text' => '<em>Diagnostic goals</em><script>alert("unsafe")</script><strong>A thoughtful conversation.</strong>',
        ]]);

        $response = $this->get('/');

        $response->assertSee('<em>Diagnostic goals</em>', false)
            ->assertSee('<strong>A thoughtful conversation.</strong>', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false)
            ->assertSeeText('within 48 hours after completing your diagnostic')
            ->assertSeeText('No obligation to purchase a package afterward.');
    }

    public function test_homepage_renders_multiple_resource_previews_from_the_supplied_inventory(): void
    {
        $resources = ResourceFactory::new()->count(3)->sequence(
            ['title' => 'Alphabet preview', 'slug' => 'alphabet-preview'],
            ['title' => 'Reading preview', 'slug' => 'reading-preview'],
            ['title' => 'Grammar preview', 'slug' => 'grammar-preview'],
        )->make(['category_id' => null, 'short_description' => 'Learning resource preview.']);
        View::composer('public.home', function (\Illuminate\View\View $view) use ($resources): void {
            $view->with('featuredResources', $resources);
        });

        $response = $this->get('/');

        foreach (['alphabet-preview', 'reading-preview', 'grammar-preview'] as $slug) {
            $response->assertSee(url('/resources/'.$slug));
        }
        $response->assertDontSeeText('New learning materials will appear here when published.')
            ->assertSeeText('Free · Name and email required');
    }

    public function test_compact_game_action_keeps_the_external_notice_and_destination_accessible(): void
    {
        $game = new Game(['title' => '6-Word Story', 'slug' => 'external-story', 'status' => 'available', 'target_url' => 'https://example.test/story']);
        $game->id = 42;
        $game->setRelation('translations', $game->newCollection());

        $view = $this->blade('<x-public-game-card :game="$game" :compact="true" />', ['game' => $game]);

        $document = new DOMDocument;
        @$document->loadHTML((string) $view);
        $xpath = new DOMXPath($document);
        $link = $xpath->query('//a[@href="https://example.test/story"]')->item(0);
        $this->assertNotNull($link);
        $this->assertSame('Play 6-Word Story', trim($link->textContent));
        $this->assertSame('_blank', $link->getAttribute('target'));
        $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
        $noticeId = $link->getAttribute('aria-describedby');
        $this->assertSame('Opens on another website', $xpath->query('//p[@id="'.$noticeId.'"]')->item(0)?->textContent);
    }
}
