<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\ContentRevision;
use App\Domains\Games\Models\Game;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LocalStoryGameTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['en', '/games', '/games/6-word-story', '6-Word Story', 'Language Games', 'Speaking & Storytelling'])]
    #[TestWith(['fr', '/fr/jeux', '/fr/jeux/6-word-story', '6 mots, une histoire', 'Jeux linguistiques', 'Parler et raconter'])]
    #[TestWith(['de', '/de/spiele', '/de/spiele/6-word-story', '6 Wörter, eine Geschichte', 'Sprachspiele', 'Sprechen und Erzählen'])]
    public function test_story_titles_badges_and_listing_copy_are_localized_without_an_english_fallback(string $locale, string $libraryUrl, string $gameUrl, string $title, string $category, string $badge): void
    {
        Game::create(['slug' => '6-word-story', 'title' => '6-Word Story', 'description' => config('public_content.game_feature.description'), 'badge' => 'Speaking & Storytelling', 'status' => 'available']);

        $this->get($gameUrl)->assertSee('<title>'.$title.' — '.$category, false);
        $listing = $this->get($libraryUrl)->assertSeeText($title)->assertSeeText($badge)->assertViewHas('isFallback', false);
        if ($locale !== 'en') {
            $listing->assertDontSeeText('Speaking & Storytelling')->assertDontSee('<title>Language Games', false);
        }
    }

    #[TestWith(['/games/6-word-story', 'en'])]
    #[TestWith(['/fr/jeux/6-word-story', 'fr'])]
    #[TestWith(['/de/spiele/6-word-story', 'de'])]
    public function test_story_uses_local_renderer_with_an_independent_interface_locale(string $url, string $locale): void
    {
        $game = Game::create(['slug' => '6-word-story', 'title' => 'Story', 'status' => 'available', 'target_url' => 'https://mohamedateff.com/6word']);
        $this->get($url)->assertOk()->assertViewIs('public.games.six-word-story')
            ->assertSee('data-ui-locale="'.$locale.'"', false)->assertSee('data-playable="true"', false)
            ->assertDontSee('5 Essential Phrases');
        $this->assertSame($game->target_url, $game->fresh()->target_url);
        $this->get(localized_url('games'))->assertOk()->assertSee('href="'.localized_url('game.detail', $game->slug).'"', false)->assertDontSeeText('Opens on another website in a new tab.');
    }

    public function test_other_local_and_external_games_keep_their_own_experiences(): void
    {
        Game::create(['slug' => 'phrase-quiz', 'title' => 'Phrases', 'status' => 'available']);
        Game::create(['slug' => 'other-story', 'title' => 'Another game', 'status' => 'available', 'target_url' => 'https://example.org/play']);
        $this->get('/games/phrase-quiz')->assertOk()->assertViewIs('public.games.show')->assertSeeText('5 Essential Phrases');
        $this->get('/games/other-story')->assertRedirect('https://example.org/play');
    }

    public function test_unavailable_story_does_not_offer_a_playable_mount_or_tracking(): void
    {
        $game = Game::create(['slug' => '6-word-story', 'title' => 'Story', 'status' => 'coming_soon']);
        $this->get('/games/'.$game->slug)->assertOk()->assertSee('data-playable="false"', false)->assertSee('data-tracking-enabled="false"', false);
        $this->postJson(route('games.track', $game->slug), ['action' => 'game_started'])->assertNotFound();
        $game->update(['status' => 'disabled']);
        $this->get('/games/'.$game->slug)->assertNotFound();
    }

    public function test_private_preview_uses_story_renderer_and_does_not_enable_telemetry(): void
    {
        $game = Game::create(['slug' => '6-word-story', 'title' => 'Live story', 'status' => 'available', 'target_url' => 'https://example.org/old']);
        ContentRevision::create(['revisable_type' => Game::class, 'revisable_id' => $game->id, 'revision_number' => 1, 'title' => 'Draft story', 'content' => ['target_url' => null], 'status' => 'draft']);
        $preview = URL::temporarySignedRoute('games.preview', now()->addHour(), ['slug' => $game->slug]);
        $this->get($preview)->assertForbidden();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))->get($preview)->assertOk()
            ->assertViewIs('public.games.six-word-story')->assertSee('data-tracking-enabled="false"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
