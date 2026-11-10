<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\WebsiteCopy;
use App\Domains\CMS\Services\WebsiteCopyCatalog;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicResourcesLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_catalogue_is_honest_and_omits_discovery_while_keeping_supporting_copy(): void
    {
        $response = $this->get('/resources');

        $response->assertOk()->assertSeeText('No resources are available to browse just yet.')->assertSeeText("Additional learning materials will appear here as they're published.")->assertDontSeeText('0 resources')->assertSeeText('Learn alongside a video')->assertSeeText('Practice independently')->assertSeeText('Come back whenever you need to')->assertSeeText('When a resource accompanies an educational video, you can use the material while watching and return to it afterward.')->assertDontSeeText('video series is planned');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(0, $xpath->query('//form[@role="search"]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="category"]')->length);
        $this->assertSame(0, $xpath->query('//article[@data-resource-id]')->length);
        $this->get('/resources?q=anything&category=missing')->assertSeeText('No resources are available to browse just yet.')->assertDontSeeText('No resources match your search.');
    }

    public function test_only_public_catalogue_records_contribute_cards_counts_and_categories(): void
    {
        $visible = $this->resource(['title' => 'Visible reference', 'file_type' => 'zip']);
        foreach ([['status' => 'draft'], ['status' => 'archived'], ['published_at' => now()->addDay()], ['published_at' => null], ['public_catalogue' => false]] as $index => $state) {
            $this->resource(['title' => 'Excluded material '.$index, ...$state]);
        }
        $deleted = $this->resource(['title' => 'Deleted material']);
        $deleted->delete();

        $response = $this->get('/resources');

        $response->assertSeeText('1 resource')->assertSeeText('Visible reference')->assertDontSeeText('Excluded material')->assertDontSeeText('Deleted material')->assertSeeText('ZIP')->assertSeeText('Free · Name and email required');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//article[@data-resource-id]')->length);
        $this->assertSame(2, $xpath->query('//select/option')->length);
        $this->assertSame(url('/resources/'.$visible->slug), $xpath->evaluate('string(//article//h3/a/@href)'));
        $this->assertSame(0, $xpath->query('//article//a[contains(@href,"download") or contains(@href,"example.test")]')->length);
    }

    #[TestWith(['TITLE NEEDLE', 'title'])]
    #[TestWith(['description needle', 'short_description'])]
    #[TestWith(['category needle', 'category'])]
    public function test_search_uses_displayed_fields_before_pagination(string $keyword, string $field): void
    {
        $category = ResourceCategory::create(['name' => 'Category needle', 'slug' => 'recorded-category', 'active' => true]);
        $resource = $this->resource(['category_id' => $category->id, 'title' => 'Title needle', 'short_description' => 'Description needle']);
        $this->resource(['title' => 'Unrelated entry']);

        $response = $this->get('/resources?'.http_build_query(['q' => '  '.$keyword.'  ']));

        $response->assertSeeText('1 resource')->assertSeeText($resource->title)->assertDontSeeText('Unrelated entry')->assertSee('name="robots" content="noindex, nofollow, noarchive"', false);
        $this->assertSame($keyword, $this->xpath($response->getContent())->evaluate('string(//input[@name="q"]/@value)'));
    }

    public function test_published_stale_translations_and_english_fallback_match_exactly_what_cards_show(): void
    {
        $translated = $this->resource(['title' => 'Invisible base title', 'short_description' => 'Invisible base description']);
        $translated->translations()->create(['locale' => 'fr', 'status' => 'stale', 'title' => 'Titre visible', 'short_description' => 'Explication visible']);
        $translated->category->translations()->create(['locale' => 'fr', 'status' => 'published', 'name' => 'Catégorie visible']);
        $fallback = $this->resource(['title' => 'Another hidden base']);
        $fallback->translations()->create(['locale' => 'en', 'status' => 'published', 'title' => 'English reference', 'short_description' => 'Fallback phrase']);
        $fallback->translations()->create(['locale' => 'fr', 'status' => 'draft', 'title' => 'Secret title', 'short_description' => 'Secret phrase']);
        $fallback->translations()->create(['locale' => 'de', 'status' => 'published', 'title' => '', 'short_description' => null]);

        foreach (['titre', 'explication', 'catégorie'] as $keyword) {
            $this->get('/fr/ressources?q='.urlencode($keyword))->assertSeeText('1 ressource')->assertSeeText('Titre visible')->assertSeeText('Explication visible')->assertDontSeeText('Invisible base');
        }
        foreach (['Invisible base', 'Secret phrase', 'Secret title'] as $keyword) {
            $this->get('/fr/ressources?q='.urlencode($keyword))->assertSeeText('0 ressources');
        }
        $french = $this->get('/fr/ressources?q=fallback')->assertSeeText('English reference')->assertSee('href="'.url('/fr/ressources/'.$fallback->slug).'"', false);
        $german = $this->get('/de/ressourcen?q=English')->assertSeeText('English reference');
        $this->assertSame(url('/fr/jeux'), $this->xpath($french->getContent())->evaluate('string(//section[@id="resources-section-4"]/a/@href)'));
        $this->assertSame(url('/de/spiele'), $this->xpath($german->getContent())->evaluate('string(//section[@id="resources-section-4"]/a/@href)'));
        $this->get('/de/ressourcen?q=Another+hidden+base')->assertSeeText('0 Lernmaterialien');
    }

    #[TestWith(['%'])]
    #[TestWith(['_'])]
    #[TestWith(["What's"])]
    #[TestWith(['C:\\notes'])]
    #[TestWith(['!'])]
    public function test_search_special_characters_are_literal(string $keyword): void
    {
        $this->resource(['title' => 'Literal '.$keyword.' reference']);
        $this->resource(['title' => 'Other reference']);

        $this->get('/resources?'.http_build_query(['q' => $keyword]))->assertSeeText('1 resource')->assertSeeText('Literal '.$keyword.' reference')->assertDontSeeText('Other reference');
    }

    public function test_category_and_search_combine_and_options_survive_zero_matches(): void
    {
        $one = ResourceCategory::create(['name' => 'First category', 'slug' => 'first', 'active' => true]);
        $two = ResourceCategory::create(['name' => 'Second category', 'slug' => 'second', 'active' => true]);
        $this->resource(['category_id' => $one->id, 'title' => 'Grammar guide']);
        $this->resource(['category_id' => $two->id, 'title' => 'Grammar review']);
        $inactive = ResourceCategory::create(['name' => 'Private category name', 'slug' => 'inactive', 'active' => false]);
        $this->resource(['category_id' => $inactive->id, 'title' => 'Unlisted category guide']);

        $this->get('/resources?q=grammar&category=first')->assertSeeText('1 resource')->assertSeeText('Grammar guide')->assertDontSeeText('Grammar review');
        $response = $this->get('/resources?q=not-found&category=first');
        $response->assertSeeText('No resources match your search.')->assertSeeText('Second category');
        $this->assertSame(1, $this->xpath($response->getContent())->query('//div[@id="resource-results"]//a')->length);
        $this->get('/resources?q=grammar&category=')->assertSeeText('2 resources');
        foreach (['inactive', 'unknown', '<img src=x>'] as $slug) {
            $response = $this->get('/resources?'.http_build_query(['category' => $slug]));
            $response->assertSeeText('No resources in this category yet')->assertDontSeeText('Private category name')->assertDontSee('<img src=x>', false);
            $this->assertSame($slug, $this->xpath($response->getContent())->evaluate('string(//select/option[@selected]/@value)'));
        }
    }

    #[TestWith(['q', ['bad']])]
    #[TestWith(['q', 'oversize'])]
    #[TestWith(['category', ['bad']])]
    #[TestWith(['category', 'oversize'])]
    public function test_invalid_filters_have_inline_recovery_and_retain_independent_input(string $field, mixed $invalid): void
    {
        if ($invalid === 'oversize') {
            $invalid = str_repeat('x', $field === 'q' ? 201 : 256);
        }
        $category = ResourceCategory::create(['name' => 'Available category', 'slug' => 'available', 'active' => true]);
        $this->resource(['category_id' => $category->id, 'title' => 'Valid phrase']);
        $input = ['q' => 'Valid', 'category' => 'available', $field => $invalid];

        $response = $this->get('/resources?'.http_build_query($input));

        $response->assertOk()->assertSee('aria-invalid="true"', false)->assertSeeText('Clear Filters')->assertSeeText('1 resource');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame($field === 'q' ? '' : 'Valid', $xpath->evaluate('string(//input[@name="q"]/@value)'));
        $this->assertSame($field === 'category' ? '' : 'available', $xpath->evaluate('string(//select/option[@selected]/@value)'));
    }

    public function test_pagination_is_stable_preserves_filters_and_promotes_only_one_eligible_first_page_item(): void
    {
        $category = ResourceCategory::create(['name' => 'Shared category', 'slug' => 'shared', 'active' => true]);
        $items = [];
        foreach (range(1, 13) as $index) {
            $items[] = $this->resource(['category_id' => $category->id, 'title' => sprintf('Reference %02d', $index), 'sort_order' => 0, 'featured' => in_array($index, [2, 3, 13], true)]);
        }
        $response = $this->get('/resources');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(12, $xpath->query('//article[@data-resource-id]')->length);
        $this->assertSame(1, $xpath->query('//article[contains(@class,"resource-library-featured")]')->length);
        $this->assertSame((string) $items[1]->id, $xpath->evaluate('string(//article[contains(@class,"resource-library-featured")]/@data-resource-id)'));
        $this->assertSame(1, $xpath->query('//article[@data-resource-id="'.$items[1]->id.'"]')->length);
        $this->get('/resources?page=2')->assertSeeText('Reference 13')->assertDontSee('resource-library-featured', false)->assertDontSeeText('Reference 12');
        $filtered = $this->get('/fr/ressources?q=Reference&category=shared');
        $filtered->assertSeeText('13 ressources')->assertDontSee('resource-library-featured', false);
        $links = $this->xpath($filtered->getContent())->query('//div[@class="resources-pagination"]//a[@href]');
        foreach ($links as $link) {
            $this->assertStringContainsString('/fr/ressources?', $link->getAttribute('href'));
            $this->assertStringContainsString('q=Reference', $link->getAttribute('href'));
            $this->assertStringContainsString('category=shared', $link->getAttribute('href'));
        }
        $this->get('/resources?q=13&lang=en')->assertSeeText('1 resource')->assertSeeText('Reference 13');
    }

    public function test_feature_requires_a_supported_destination_and_library_cover_format_are_recorded(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('test/reference.txt', 'Synthetic isolated resource.');
        $this->resource(['title' => 'Broken feature', 'featured' => true, 'external_url' => 'javascript:alert(1)', 'file_type' => null]);
        $valid = $this->resource(['title' => 'Local feature', 'featured' => true, 'external_url' => null, 'file_path' => 'test/reference.txt', 'cover_image_path' => 'missing.webp', 'file_type' => 'txt']);

        $response = $this->get('/resources');

        $xpath = $this->xpath($response->getContent());
        $this->assertSame((string) $valid->id, $xpath->evaluate('string(//article[contains(@class,"resource-library-featured")]/@data-resource-id)'));
        $this->assertSame(0, $xpath->query('//article//img')->length);
        $response->assertSeeText('TXT')->assertSeeText('Learning material')->assertDontSeeText('PDF');
    }

    public function test_private_paths_external_destinations_and_full_documents_are_not_searchable(): void
    {
        $this->resource(['title' => 'Visible reference', 'external_url' => 'https://example.test/destination-secret', 'file_path' => 'private/path-secret.txt', 'full_description' => 'Full-document-secret']);

        foreach (['destination-secret', 'path-secret', 'Full-document-secret'] as $keyword) {
            $this->get('/resources?q='.$keyword)->assertSeeText('0 resources')->assertDontSeeText('Visible reference');
        }
    }

    public function test_resolvable_public_cover_has_reserved_dimensions_and_detail_links(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('covers/synthetic.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400"></svg>');
        $resource = $this->resource(['title' => 'Recorded cover', 'cover_image_path' => 'covers/synthetic.svg', 'file_type' => 'svg']);

        $response = $this->get('/resources');

        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//article//img[@width="640" and @height="400" and @loading="lazy"]')->length);
        $this->assertSame('Recorded cover', $xpath->evaluate('string(//article//img/@alt)'));
        $this->assertSame(url('/resources/'.$resource->slug), $xpath->evaluate('string(//article//h3/a/@href)'));
    }

    public function test_cms_section_controls_wording_extras_and_localized_links_are_preserved(): void
    {
        $this->resource();
        WebsiteCopy::create(['page' => 'resources', 'locale' => 'en', 'published_content' => ['resources__1__title' => 'Edited library heading', 'resources__0__blocks__4__text' => 'Edited companion condition.', '_sections__resources__0__visible' => false, '_sections__resources__4__order' => 1, '_sections__resources__1__order' => 2, '_sections__resources__2__extra' => '<p>Additional CMS guidance.</p><script>alert(1)</script>', 'ui__'.sha1('Search resources') => 'Find a resource', 'links__games' => '/games/custom-destination']]);
        $response = $this->get('/fr/ressources');

        $response->assertSeeText('Edited library heading')->assertSeeText('Find a resource')->assertSeeText('Additional CMS guidance.')->assertDontSee('<script>alert(1)</script>', false)->assertDontSeeText('Sometimes, the right explanation')->assertSeeInOrder(['Faites travailler votre arabe.', 'Edited library heading'])->assertSee('href="'.url('/games/custom-destination').'"', false)->assertDontSee('hreflang="fr"', false);
        $fields = app(WebsiteCopyCatalog::class)->fields('resources');
        foreach (['resources__0__blocks__4__text', 'resources__1__blocks__0__text', 'resources__4__title', 'ui__'.sha1('View Resource')] as $key) {
            $this->assertArrayHasKey($key, $fields);
        }
        config(['public_content.resources.2.blocks.10' => ['type' => 'paragraph', 'text' => 'Unfamiliar additional block.']]);
        $this->get('/resources?lang=en')->assertSeeText('Unfamiliar additional block.');
    }

    public function test_default_shared_card_and_resource_copy_preview_remain_separate(): void
    {
        $resource = $this->resource(['title' => 'Shared card unchanged']);
        $html = Blade::render('<x-public-resource-card :resource="$resource" />', ['resource' => $resource->load('translations', 'category.translations')]);
        $this->assertStringContainsString('Get Free Access', $html);
        $this->assertStringNotContainsString('resource-library-card', $html);
        $this->get(route('admin.website-copy.preview', ['page' => 'resources', 'locale' => 'en']))->assertRedirect(route('admin.login'));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))->get(route('admin.website-copy.preview', ['page' => 'resources', 'locale' => 'en']))->assertOk()->assertSeeText('Shared card unchanged')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_empty_catalogue_retains_escaped_context_errors_and_one_recovery_without_absent_controls(): void
    {
        $hidden = ResourceCategory::create(['name' => 'Hidden managed name', 'slug' => 'hidden', 'active' => true]);
        $this->resource(['category_id' => $hidden->id, 'public_catalogue' => false]);
        foreach ([['q' => '<script>needle</script>', 'category' => 'hidden'], ['q' => ['bad'], 'category' => 'hidden'], ['q' => 'valid phrase', 'category' => ['bad']]] as $query) {
            $response = $this->get('/resources?'.http_build_query($query))->assertOk()->assertSeeText('No resources are available to browse just yet.')->assertDontSeeText('Hidden managed name')->assertDontSeeText('0 resources')->assertDontSee('<script>needle</script>', false);
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(0, $xpath->query('//form[@role="search"] | //input[@name="q"] | //select[@name="category"] | //*[@aria-describedby="resource-search-error" or @aria-describedby="resource-category-error"]')->length);
            $this->assertSame(1, $xpath->query('//div[@id="resource-results"]//a')->length);
            $this->assertSame(url('/resources').'#resource-results', $xpath->evaluate('string(//div[@id="resource-results"]//a/@href)'));
            if (is_array($query['q'])) {
                $response->assertSeeText('Enter a search of up to 200 characters.')->assertSeeText('In hidden');
            } elseif (is_array($query['category'])) {
                $response->assertSeeText('Choose a category of up to 255 characters.')->assertSeeText('valid phrase');
            } else {
                $response->assertSeeText('<script>needle</script>');
            }
        }
    }

    #[TestWith([1])]
    #[TestWith([2])]
    #[TestWith([12])]
    #[TestWith([13])]
    #[TestWith([25])]
    public function test_page_membership_ranges_and_boundaries_remain_stable(int $count): void
    {
        $items = [];
        foreach (range(1, $count) as $index) {
            $items[] = $this->resource(['title' => sprintf('Ordered %02d', $index), 'sort_order' => 0]);
        }
        $last = (int) ceil($count / 12);
        foreach (range(1, $last) as $page) {
            $response = $this->get('/resources?page='.$page)->assertOk();
            $xpath = $this->xpath($response->getContent());
            $actual = [];
            foreach ($xpath->query('//article/@data-resource-id') as $node) {
                $actual[] = (int) $node->nodeValue;
            }
            $this->assertSame(array_map(fn (Resource $item): int => $item->id, array_slice($items, ($page - 1) * 12, 12)), $actual);
            $response->assertSeeText('Showing '.(($page - 1) * 12 + 1).'–'.min($page * 12, $count).' of '.$count);
            if ($last > 1) {
                $this->assertSame('Page '.$page.' of '.$last, $xpath->evaluate('string(//nav[@class="resources-pager"]/*[@aria-current="page"])'));
                $this->assertSame($page > 1 ? 1 : 0, $xpath->query('//nav[@class="resources-pager"]//a[@rel="prev"]')->length);
                $this->assertSame($page < $last ? 1 : 0, $xpath->query('//nav[@class="resources-pager"]//a[@rel="next"]')->length);
                foreach ($xpath->query('//nav[@class="resources-pager"]//a') as $link) {
                    $this->assertStringEndsWith('#resource-results', $link->getAttribute('href'));
                }
            }
        }
    }

    public function test_stale_pages_redirect_to_last_match_preserving_normalized_filters_and_locale(): void
    {
        $category = ResourceCategory::create(['name' => 'Review', 'slug' => 'review', 'active' => true]);
        foreach (range(1, 13) as $index) {
            $this->resource(['category_id' => $category->id, 'title' => 'Review '.$index]);
        }
        $destination = url('/fr/ressources').'?q=Review&category=review&page=2#resource-results';
        $this->get('/fr/ressources?q=%20Review%20&category=review&page=90')->assertStatus(302)->assertRedirect($destination);
        $this->get('/fr/ressources?q=Review&category=review&page=999999999999999999')->assertStatus(302)->assertRedirect($destination);
        $this->get($destination)->assertOk()->assertSeeText('13–13 sur 13')->assertDontSeeText('No resources match');
        $one = url('/de/ressourcen').'?q=Review+13&category=review#resource-results';
        $this->get('/de/ressourcen?q=Review+13&category=review&page=2')->assertRedirect($one);
        $this->get($one)->assertOk()->assertSeeText('Review 13');
        $none = url('/resources').'?q=absent#resource-results';
        $this->get('/resources?q=absent&page=8&lang=en')->assertRedirect($none);
        $this->get($none)->assertOk()->assertSeeText('No resources match your search.');
    }

    public function test_later_page_of_globally_empty_catalogue_recovers_without_a_loop(): void
    {
        $this->get('/resources?page=9')->assertStatus(302)->assertRedirect(url('/resources').'#resource-results');
        $this->get('/resources#resource-results')->assertOk()->assertSeeText('No resources are available to browse just yet.');
    }

    #[TestWith(['nonsense'])]
    #[TestWith([['2']])]
    #[TestWith(['0'])]
    #[TestWith(['-2'])]
    #[TestWith(['1.5'])]
    public function test_invalid_page_values_resolve_to_first_page(mixed $page): void
    {
        $resource = $this->resource(['title' => 'First page reference']);
        $response = $this->get('/resources?'.http_build_query(['page' => $page]))->assertOk()->assertSeeText('First page reference');
        $this->assertSame((string) $resource->id, $this->xpath($response->getContent())->evaluate('string(//article/@data-resource-id)'));
    }

    #[TestWith([false, false])]
    #[TestWith([true, false])]
    #[TestWith([false, true])]
    #[TestWith([true, true])]
    public function test_single_result_uses_featured_presentation_only_for_explicit_unfiltered_feature(bool $featured, bool $filtered): void
    {
        $this->resource(['title' => 'Single reference', 'featured' => $featured]);
        $response = $this->get('/resources'.($filtered ? '?q=Single' : ''))->assertOk()->assertSeeText('1 resource')->assertSeeText('Free · Name and email required');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//article[@data-resource-id]')->length);
        $this->assertSame($featured && ! $filtered ? 1 : 0, $xpath->query('//article[contains(@class,"resource-library-featured")]')->length);
        $this->assertSame(0, $xpath->query('//article//div[@class="resource-library-media"]')->length);
    }

    public function test_game_invitation_preview_requires_corresponding_available_game(): void
    {
        $this->get('/resources')->assertDontSee('class="resources-game-image"', false)->assertSeeText('Explore Arabic Games');
        $game = Game::create(['title' => '6-Word Story', 'slug' => '6-word-story', 'status' => 'available']);
        $this->get('/resources')->assertSee('class="resources-game-image"', false);
        $game->update(['status' => 'coming_soon']);
        $this->get('/resources')->assertDontSee('class="resources-game-image"', false)->assertSeeText('Explore Arabic Games');
    }

    private function resource(array $attributes = []): Resource
    {
        return ResourceFactory::new()->create(['title' => 'Public reference', 'short_description' => 'A useful explanation.', 'public_catalogue' => true, ...$attributes]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}
