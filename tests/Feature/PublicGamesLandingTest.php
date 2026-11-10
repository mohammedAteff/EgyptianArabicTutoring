<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\WebsiteCopy;
use App\Domains\CMS\Services\WebsiteCopyCatalog;
use App\Domains\CMS\Services\WebsiteCopyService;
use App\Domains\Games\Models\Game;
use Database\Factories\AdministratorFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicGamesLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_game_identity_media_launch_and_explanation_are_rendered_once(): void
    {
        $game = $this->game(['title' => 'Configured story title', 'description' => 'Configured short game description.', 'badge' => 'Configured badge', 'slug' => '6-word-story', 'thumbnail_path' => 'images/games/6-word-story.webp']);
        $response = $this->get('/games')->assertOk()->assertSeeText('Configured story title')->assertSeeText('Configured badge')->assertSeeText('Six Words. One Story. Your Imagination.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//main//h1')->length);
        $this->assertSame(1, $xpath->query('//article[@data-game-id="'.$game->id.'"]')->length);
        $this->assertSame(1, $xpath->query('//article//a')->length);
        $this->assertSame(1, $xpath->query('//article//p[text()="Configured short game description."]')->length);
        $link = $xpath->query('//article//a')->item(0);
        $this->assertSame(localized_url('game.detail', $game->slug), $link->getAttribute('href'));
        $this->assertSame('', $link->getAttribute('target'));
        $this->assertSame('', $link->getAttribute('rel'));
        $this->assertSame('Play Configured story title', trim($link->textContent));
        $this->assertSame('', $link->getAttribute('onclick'));
        $this->assertSame('', $link->getAttribute('aria-describedby'));
        $this->assertSame(1, $xpath->query('//article//img[@width and @height]')->length);
        $this->assertStringContainsString('six-word-story-cover.webp', $xpath->evaluate('string(//article//img/@src)'));
        $this->assertSame(0, $xpath->query('//main//iframe | //main//form | //article//button')->length);
        $response->assertSeeText('Connected Story: Build a narrative')->assertSeeText('Wild Mix: Turn seemingly unrelated')->assertSeeText('Four difficulty levels: From Easy to Extreme');
        $response->assertSeeText('Think Creatively')->assertSeeText('Keep Your Skills Active')->assertSeeText('Enjoy the Challenge')->assertSeeText("You don't need to be enrolled in private lessons to enjoy the games.");
    }

    public function test_multiple_games_keep_their_order_with_only_one_feature_and_story_copy_on_the_correct_record(): void
    {
        $first = $this->game(['title' => 'Ordered first', 'sort_order' => 1, 'target_url' => 'https://example.test/6word']);
        $story = $this->game(['title' => 'Ordered second', 'slug' => '6-word-story', 'sort_order' => 2, 'featured' => true]);
        $last = $this->game(['title' => 'Ordered third', 'sort_order' => 3, 'featured' => true]);
        foreach (['draft', 'disabled', 'archived'] as $state) {
            $this->game(['title' => 'Excluded '.$state, 'status' => $state]);
        }
        $response = $this->get('/games')->assertOk()->assertViewHas('featuredGameId', $story->id)->assertSeeInOrder(['Ordered first', 'Ordered second', 'Ordered third'])->assertDontSeeText('Excluded');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame([$first->id, $story->id, $last->id], array_map(fn ($node): int => (int) $node->nodeValue, iterator_to_array($xpath->query('//article/@data-game-id'))));
        $this->assertSame(1, $xpath->query('//article[@data-game-id="'.$story->id.'"]//details')->length);
        $this->assertSame(0, $xpath->query('//article[@data-game-id="'.$first->id.'" or @data-game-id="'.$last->id.'"]//details')->length);
        $this->assertSame(1, $xpath->query('//article[@data-featured="true"]')->length);
        $this->assertSame((string) $story->id, $xpath->evaluate('string(//article[@data-featured="true"]/@data-game-id)'));
        $this->assertSame(3, $xpath->query('//article//a')->length);
    }

    public function test_single_available_game_can_be_wide_without_changing_its_featured_flag(): void
    {
        $game = $this->game(['featured' => false]);
        $response = $this->get('/games')->assertOk()->assertViewHas('wideGameId', $game->id)->assertViewHas('featuredGameId', null);
        $this->assertSame(0, $this->xpath($response->getContent())->query('//article[@data-featured="true"]')->length);
        $this->assertFalse($game->fresh()->featured);
    }

    public function test_coming_soon_featured_records_cannot_take_the_available_game_spotlight(): void
    {
        $this->game(['featured' => true, 'status' => 'coming_soon', 'sort_order' => 0]);
        $first = $this->game(['featured' => false, 'sort_order' => 1]);
        $selected = $this->game(['featured' => true, 'sort_order' => 2]);
        $this->game(['featured' => true, 'status' => 'draft', 'sort_order' => -1]);

        $response = $this->get('/games')->assertOk()->assertViewHas('featuredGameId', $selected->id)->assertViewHas('wideGameId', $selected->id);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(3, $xpath->query('//article[@data-game-id]')->length);
        $this->assertSame(2, $xpath->query('//article//a')->length);
        $this->assertSame('false', $xpath->evaluate('string(//article[@data-game-id="'.$first->id.'"]/@data-featured)'));
    }

    public function test_story_editorial_line_and_essential_options_stay_outside_the_collapsed_explanation(): void
    {
        $this->game(['slug' => '6-word-story', 'featured' => true]);

        $response = $this->get('/games')->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//article//p[text()="Six Words. One Story. Your Imagination."]')->length);
        $this->assertSame(0, $xpath->query('//article//details[@open]')->length);
        $this->assertSame('More about the challenge', trim($xpath->evaluate('string(//article//details/summary)')));
        $this->assertSame(4, $xpath->query('//article//details//p')->length);
        $this->assertSame(0, $xpath->query('//article//details//a')->length);
        $this->assertSame(0, $xpath->query('//article//details//*[contains(text(), "Connected Story:") or contains(text(), "Wild Mix:") or contains(text(), "Four difficulty levels:") or contains(text(), "Multiple languages:")]')->length);
        $this->assertSame(1, substr_count($response->getContent(), 'Can you turn a series of words into a story that makes sense?'));
    }

    public function test_jump_and_disclosure_labels_are_editable_and_hidden_library_has_no_shortcut(): void
    {
        $this->game(['slug' => '6-word-story']);
        $copy = WebsiteCopy::create(['page' => 'games', 'locale' => 'en', 'published_content' => [
            'ui__'.sha1('Jump to games') => 'Go to challenges',
            'ui__'.sha1('More about the challenge') => 'Read the story guidance',
        ]]);

        $response = $this->get('/games')->assertOk()->assertSeeText('Go to challenges')->assertSeeText('Read the story guidance');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('#games-section-1', $xpath->evaluate('string(//a[contains(text(), "Go to challenges")]/@href)'));
        $this->assertSame(1, $xpath->query('//section[@id="games-section-1"]')->length);

        $copy->update(['published_content' => [...$copy->published_content, '_sections__games__1__visible' => false]]);
        $this->get('/games')->assertOk()->assertDontSeeText('Go to challenges')->assertDontSeeText('Read the story guidance');
    }

    public function test_additional_feature_content_remains_in_the_disclosure_when_default_paragraphs_are_removed(): void
    {
        $this->game(['slug' => '6-word-story']);
        config([
            'public_content.game_feature.paragraphs' => [],
            'public_content.game_feature.blocks' => [['type' => 'richtext', 'text' => '<p>Custom <strong>challenge block</strong>.</p>']],
            'public_content.game_feature.extra_content' => '<p>Additional challenge guidance.</p><script>alert(1)</script>',
        ]);

        $response = $this->get('/games')->assertOk()->assertSee('<strong>challenge block</strong>', false)->assertSeeText('Additional challenge guidance.')->assertDontSee('<script>alert(1)</script>', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//article//details//strong[text()="challenge block"]')->length);
        $this->assertSame(1, $xpath->query('//article//details//p[text()="Additional challenge guidance."]')->length);
        $this->assertSame(0, $xpath->query('//article//details[@open]')->length);
    }

    public function test_empty_catalogue_and_coming_soon_states_never_expose_a_play_action_or_orphaned_story(): void
    {
        $this->get('/games')->assertOk()->assertSeeText('New challenges are on their way.')->assertSeeText('Explore Arabic Resources')->assertDontSeeText('Six Words. One Story.');
        $this->game(['title' => 'Future story', 'slug' => '6-word-story', 'status' => 'coming_soon', 'featured' => true]);
        $response = $this->get('/games')->assertOk()->assertSeeText('New challenges are on their way.')->assertSeeText('Future story')->assertSeeText('Coming Soon')->assertDontSeeText('Six Words. One Story.')->assertDontSeeText('Free to Play');
        $this->assertSame(0, $this->xpath($response->getContent())->query('//article//a')->length);
    }

    #[TestWith([null])]
    #[TestWith(['images/games/absent.webp'])]
    #[TestWith(['../AGENTS.md'])]
    #[TestWith(['assets/fonts/LICENSE-Newsreader.txt'])]
    #[TestWith(['https://example.test/remote.webp'])]
    public function test_invalid_or_missing_media_keeps_the_game_and_launch_accessible(?string $path): void
    {
        $this->game(['thumbnail_path' => $path]);
        $response = $this->get('/games')->assertOk()->assertSeeText('Play Test challenge');
        $this->assertSame(0, $this->xpath($response->getContent())->query('//article//img')->length);
    }

    public function test_unsafe_external_destination_is_not_an_active_play_link(): void
    {
        $this->game(['target_url' => 'javascript:alert(1)']);
        $response = $this->get('/games')->assertOk()->assertSeeText("This game isn't available right now.");
        $this->assertSame(0, $this->xpath($response->getContent())->query('//article//a')->length);
    }

    public function test_internal_game_destination_uses_the_localized_route_without_external_guidance(): void
    {
        $game = $this->game(['target_url' => null]);
        $response = $this->get('/de/spiele')->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(url('/de/spiele/'.$game->slug), $xpath->evaluate('string(//article//a/@href)'));
        $this->assertSame(0, $xpath->query('//article//a[@target]')->length);
        $response->assertDontSeeText('Opens on another website in a new tab.');
    }

    public function test_cms_section_identity_order_visibility_extra_content_and_editable_feature_strings_survive(): void
    {
        $this->game(['slug' => '6-word-story']);
        WebsiteCopy::create(['page' => 'games', 'locale' => 'en', 'published_content' => [
            'games__0__title' => 'Published introduction', '_sections__games__3__order' => 1, '_sections__games__0__order' => 9,
            'game_feature__heading' => 'Published explanation', 'game_feature__paragraphs__0' => 'Edited opening question.',
            'game_feature__features__0' => 'Custom label: retain this: complete configured explanation.',
            '_sections__games__2__extra' => '<p>Published extra guidance.</p><script>alert(1)</script>',
            'links__resources' => '/resources/custom', 'ui__'.sha1('Play {game}') => 'Try {game}',
        ]]);
        $response = $this->get('/games')->assertOk()->assertSeeInOrder(['Curious About the Language Behind the Game?', 'Published introduction'])->assertSeeText('Published explanation')->assertSeeText('Edited opening question.')->assertSeeText('Custom label: retain this: complete configured explanation.')->assertSeeText('Published extra guidance.')->assertSeeText('Try Test challenge')->assertDontSee('<script>alert(1)</script>', false)->assertSee('href="'.url('/resources/custom').'"', false);
        $this->assertSame(1, $this->xpath($response->getContent())->query('//main//h1')->length);
        $copy = WebsiteCopy::firstOrFail();
        $copy->update(['published_content' => [...$copy->published_content, '_sections__games__1__visible' => false]]);
        $hidden = $this->get('/games')->assertOk()->assertDontSeeText('Published explanation')->assertDontSeeText('Try Test challenge');
        $this->assertSame(0, $this->xpath($hidden->getContent())->query('//article[@data-game-id]')->length);
        $fields = app(WebsiteCopyCatalog::class)->fields('games');
        foreach (['games__0__blocks__3__text', 'game_feature__features__0', 'game_feature__paragraphs__3', 'ui__'.sha1('Opens on another website in a new tab.'), 'ui__'.sha1('Jump to games'), 'ui__'.sha1('More about the challenge')] as $key) {
            $this->assertArrayHasKey($key, $fields);
        }
    }

    public function test_customized_blocks_and_additional_feature_content_remain_accessible(): void
    {
        $this->game(['slug' => '6-word-story']);
        config(['public_content.games.2.blocks' => [
            ['type' => 'paragraph', 'text' => 'Additional opening one.'], ['type' => 'paragraph', 'text' => 'Additional opening two.'], ['type' => 'paragraph', 'text' => 'Additional opening three.'],
            ['type' => 'heading', 'text' => 'Custom benefit'], ['type' => 'richtext', 'text' => '<p>Formatted <strong>custom benefit</strong>.</p>'], ['type' => 'heading', 'text' => 'Fourth custom idea'], ['type' => 'paragraph', 'text' => 'No text discarded.'],
        ], 'public_content.game_feature.paragraphs.4' => 'Extra story paragraph.', 'public_content.game_feature.features.4' => 'Extra option text.']);
        $this->get('/games')->assertOk()->assertSeeText('Additional opening three.')->assertSeeText('Custom benefit')->assertSee('<strong>custom benefit</strong>', false)->assertSeeText('Fourth custom idea')->assertSeeText('No text discarded.')->assertSeeText('Extra story paragraph.')->assertSeeText('Extra option text.');
        config(['public_content.games.2.blocks' => [['type' => 'paragraph', 'text' => 'Unstructured custom introduction.'], ['type' => 'paragraph', 'text' => 'Unstructured custom ending.']]]);
        $this->get('/games')->assertOk()->assertSeeText('Unstructured custom introduction.')->assertSeeText('Unstructured custom ending.');
    }

    public function test_published_page_copy_does_not_claim_untranslated_game_records_are_localized(): void
    {
        $game = $this->game(['title' => 'Hidden base title']);
        $game->translations()->create(['locale' => 'en', 'status' => 'published', 'title' => 'Published English title', 'description' => 'English fallback description.']);
        $game->translations()->create(['locale' => 'fr', 'status' => 'draft', 'title' => 'Private French title', 'description' => 'Private French description.']);
        WebsiteCopy::create(['page' => 'games', 'locale' => 'fr', 'published_content' => ['games__0__title' => 'Introduction française', 'seo__title' => 'Titre SEO', 'seo__description' => 'Description SEO']]);
        $response = $this->get('/fr/jeux')->assertOk()->assertSeeText('Introduction française')->assertSeeText('Published English title')->assertDontSeeText('Private French')->assertDontSeeText('Hidden base title')->assertDontSee('hreflang="fr"', false)->assertSee('<link rel="canonical" href="'.url('/games').'">', false)->assertSee('name="description" content="Description SEO"', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('en', $xpath->evaluate('string(//article/@lang)'));
        $this->assertSame('fr', $xpath->evaluate('string(//div[@class="games-page"]/@lang)'));
        $this->assertSame('en', $xpath->evaluate('string(//article//h3/ancestor::article/@lang)'));
    }

    public function test_complete_published_or_stale_translations_participate_in_the_games_locale_cluster(): void
    {
        foreach (['published', 'stale'] as $status) {
            $game = $this->game();
            $game->translations()->create(['locale' => 'de', 'status' => $status, 'title' => 'Deutsche Herausforderung '.$status, 'description' => 'Beschreibung '.$status]);
        }
        WebsiteCopy::create(['page' => 'games', 'locale' => 'de', 'published_content' => ['games__0__title' => 'Deutsche Spiele']]);
        $response = $this->get('/de/spiele')->assertOk()->assertSeeText('Deutsche Spiele')->assertSeeText('Deutsche Herausforderung published')->assertSeeText('Deutsche Herausforderung stale')->assertSee('hreflang="de"', false)->assertSee('<link rel="canonical" href="'.url('/de/spiele').'">', false);
        $this->assertSame(2, $this->xpath($response->getContent())->query('//article[@lang="de"]')->length);
    }

    #[TestWith(['Speaking', 'Sprechen', 'de'])]
    #[TestWith(['Custom English badge', 'Custom English badge', 'en'])]
    public function test_partial_game_translation_keeps_english_field_fallback_tagged(string $badge, string $badgeText, string $badgeLocale): void
    {
        $game = $this->game(['description' => 'Older base description.', 'badge' => $badge]);
        $game->translations()->create(['locale' => 'en', 'status' => 'published', 'title' => 'English title', 'description' => 'Published English explanation.']);
        $game->translations()->create(['locale' => 'de', 'status' => 'published', 'title' => 'Deutscher Titel', 'description' => null]);
        $response = $this->get('/de/spiele')->assertOk()->assertSeeText('Deutscher Titel')->assertSeeText('Published English explanation.')->assertDontSeeText('Older base description.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('de', $xpath->evaluate('string(//article//h3/bdi/@lang)'));
        $this->assertSame('en', $xpath->evaluate('string(//article//p[@class="games-game-description"]/@lang)'));
        $this->assertSame($badgeLocale, $xpath->evaluate('string(//article//p[@class="games-game-meta"]/bdi/@lang)'));
        $this->assertSame($badgeText, $xpath->evaluate('string(//article//p[@class="games-game-meta"]/bdi)'));
    }

    public function test_games_draft_preview_is_private_and_preserves_preview_indexing_protection(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $copy = app(WebsiteCopyService::class);
        $copy->save($actor, 'games', 'en', array_replace($copy->values('games', 'en'), ['games__0__title' => 'Private Games draft', 'game_feature__heading' => 'Private feature draft']), 'draft', $copy->state('games', 'en'));
        $this->game(['slug' => '6-word-story']);
        $this->get('/games')->assertOk()->assertDontSeeText('Private Games draft')->assertDontSeeText('Private feature draft');
        $this->get(route('admin.website-copy.preview', ['page' => 'games', 'locale' => 'en']))->assertRedirect(route('admin.login'));
        $this->actingAs($actor)->get(route('admin.website-copy.preview', ['page' => 'games', 'locale' => 'en']))->assertOk()->assertSeeText('Private Games draft')->assertSeeText('Private feature draft')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_default_and_compact_shared_game_cards_keep_their_original_actions(): void
    {
        $game = $this->game();
        foreach ([false => 'Play Now', true => 'Play Test challenge'] as $compact => $label) {
            $html = Blade::render('<x-public-game-card :game="$game" :compact="$compact" />', ['game' => $game->load('translations'), 'compact' => (bool) $compact]);
            $this->assertStringContainsString($label, $html);
            $this->assertStringNotContainsString('games-game-identity', $html);
            $this->assertStringContainsString('https://example.test/play', $html);
        }
    }

    private function game(array $attributes = []): Game
    {
        return Game::create(['title' => 'Test challenge', 'slug' => fake()->unique()->slug(), 'description' => 'A configured speaking challenge.', 'badge' => 'Speaking', 'status' => 'available', 'target_url' => 'https://example.test/play', 'sort_order' => 1, ...$attributes]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}
