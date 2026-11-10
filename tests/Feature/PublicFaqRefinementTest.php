<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\WebsiteCopy;
use App\Domains\CMS\Services\TranslationService;
use App\Domains\CMS\Services\WebsiteCopyCatalog;
use Database\Factories\AdministratorFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicFaqRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_follow_the_canonical_order_and_keep_extra_and_uncategorized_questions(): void
    {
        foreach (array_reverse(config('public_content.faq_topics')) as $index => $topic) {
            $this->faq(['question' => 'Question for '.$topic, 'topic' => $topic, 'sort_order' => $index]);
        }
        $this->faq(['question' => 'Extra topic question', 'topic' => 'Community', 'sort_order' => -2]);
        $this->faq(['question' => 'Uncategorized question', 'topic' => null, 'sort_order' => -1]);
        $this->faq(['question' => 'Hidden question', 'topic' => 'Private topic', 'active' => false]);

        $response = $this->get('/faq');

        $response->assertOk()->assertSeeInOrder(['faq-group-0', '>Getting Started<', '>Diagnostic &amp; Booking<', '>Private Lessons &amp; Guidance<', '>Pricing &amp; Packages<', '>Cancellations &amp; Refunds<', '>Student Portal<', '>Resources &amp; Games<', '>Technical Help<', '>Community<', '>General<'], false)
            ->assertSeeText('10 questions')->assertSeeText('Uncategorized question')->assertDontSeeText('Hidden question')->assertDontSeeText('Private topic');
        $this->assertSame(0, $this->xpath($response->getContent())->query('//details[@open]')->length);
    }

    public function test_question_and_answer_matches_combine_with_topics_and_keep_search_results_collapsed(): void
    {
        $first = $this->faq(['question' => 'Roadmap question', 'answer' => 'First answer.', 'topic' => 'Diagnostic & Booking', 'sort_order' => 2]);
        $second = $this->faq(['question' => 'Another question', 'answer' => 'Written roadmap after meeting.', 'topic' => 'Diagnostic & Booking', 'sort_order' => 1]);
        $this->faq(['question' => 'Roadmap elsewhere', 'topic' => 'Getting Started']);

        $response = $this->get('/faq?'.http_build_query(['q' => '  ROADMAP  ', 'topic' => 'Diagnostic & Booking']));

        $response->assertSeeInOrder([$second->question, $first->question])->assertDontSeeText('Roadmap elsewhere')->assertSeeText('2 questions');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(0, $xpath->query('//details[@open]')->length);
        $this->assertSame(1, $xpath->query('//details[summary/span[contains(., "Another question")]]//span[@class="faq-answer-match"]')->length);
        $this->assertSame(0, $xpath->query('//details[summary/span[contains(., "Roadmap question")]]//span[@class="faq-answer-match"]')->length);
        $this->assertSame('ROADMAP', $xpath->evaluate('string(//input[@name="q"]/@value)'));
        $this->assertSame('Diagnostic & Booking', $xpath->evaluate('string(//select[@name="topic"]/option[@selected]/@value)'));
        $this->assertSame(1, $xpath->query('//form[@method="GET"]')->length);
        $this->assertSame(1, $xpath->query('//*[@name="topic"]')->length);
    }

    public function test_search_uses_published_translation_and_readable_english_fallback_instead_of_hidden_source_text(): void
    {
        $translated = $this->faq(['question' => 'Hidden source needle', 'answer' => 'English source only.']);
        $translated->translations()->create(['locale' => 'fr', 'status' => 'published', 'question' => 'Comment commencer ?', 'answer' => 'Un café et une feuille.']);
        $fallback = $this->faq(['question' => 'Older base text', 'answer' => 'Older base answer.']);
        $fallback->translations()->create(['locale' => 'en', 'status' => 'published', 'question' => 'Published English fallback', 'answer' => 'Readable umbrella answer.']);
        $fallback->translations()->create(['locale' => 'fr', 'status' => 'draft', 'question' => 'Private brouillon', 'answer' => 'Private secret.']);
        $inactive = $this->faq(['active' => false]);
        $inactive->translations()->create(['locale' => 'fr', 'status' => 'published', 'question' => 'Inactive café', 'answer' => 'Inactive secret.']);

        $this->get('/fr/faq?q=CAFE')->assertSeeText('Comment commencer ?')->assertSeeText('Un café et une feuille.')->assertSeeText(__('Match found in the answer.'))->assertDontSeeText('Inactive café')->assertDontSeeText('Private brouillon');
        $this->get('/fr/faq?q=needle')->assertSeeText(__('No questions match your search.'))->assertDontSeeText('Hidden source needle');
        $this->get('/fr/faq?q=umbrella')->assertSeeText('Published English fallback')->assertSeeText(__('English Fallback'))->assertSeeText(__('Match found in the answer.'))->assertDontSeeText('Older base text');
        $this->get('/fr/faq?q=brouillon')->assertSeeText(__('No questions match your search.'))->assertDontSeeText('Private brouillon')->assertDontSeeText(__('Match found in the answer.'));
    }

    #[TestWith(['published'])]
    #[TestWith(['stale'])]
    public function test_previously_published_translations_remain_searchable(string $status): void
    {
        $faq = $this->faq();
        $faq->translations()->create(['locale' => 'de', 'status' => $status, 'question' => 'Wie beginnen?', 'answer' => 'Übungen und Grüße.']);

        $this->get('/de/faq?q=UBUNGEN')->assertSeeText('Wie beginnen?')->assertSeeText('Übungen und Grüße.')->assertSeeText(__('Match found in the answer.'))->assertDontSeeText(__('English Fallback'));
    }

    public function test_empty_published_translation_falls_back_as_a_complete_question_and_answer(): void
    {
        $faq = $this->faq(['question' => 'Readable base question', 'answer' => 'Readable base answer.']);
        $faq->translations()->create(['locale' => 'fr', 'status' => 'published', 'question' => 'Incomplete translation', 'answer' => '   ']);

        $this->get('/fr/faq?q=readable')->assertSeeText('Readable base question')->assertSeeText('Readable base answer.')->assertSeeText(__('English Fallback'))->assertDontSeeText('Incomplete translation');
    }

    public function test_filtered_translation_coverage_does_not_advertise_a_fully_translated_collection(): void
    {
        $translated = $this->faq(['topic' => 'Getting Started']);
        $translated->translations()->create(['locale' => 'fr', 'status' => 'published', 'question' => 'Question traduite', 'answer' => 'Réponse publiée.']);
        $this->faq(['topic' => 'Technical Help']);
        WebsiteCopy::create(['page' => 'faq', 'locale' => 'fr', 'published_content' => ['faq_intro__title' => 'Questions en français']]);

        $this->get('/fr/faq?q=traduite')->assertSeeText('Question traduite')->assertDontSee('hreflang="fr"', false)->assertSee('<html lang="en"', false)->assertSee('name="robots" content="noindex, nofollow, noarchive"', false);
    }

    #[TestWith(['100%'])]
    #[TestWith(['under_score'])]
    #[TestWith(['C:\\notes'])]
    #[TestWith(["What's next?"])]
    public function test_special_characters_match_literal_substrings(string $keyword): void
    {
        $this->faq(['question' => 'Literal example', 'answer' => $keyword]);
        $this->faq(['question' => 'Unrelated example', 'answer' => 'Other content.']);

        $this->get('/faq?'.http_build_query(['q' => $keyword]))->assertSeeText('Literal example')->assertDontSeeText('Unrelated example')->assertSeeText('1 question');
    }

    public function test_no_results_keep_all_topics_and_offer_three_distinct_reset_actions(): void
    {
        $this->faq(['topic' => 'Getting Started']);
        $this->faq(['topic' => 'Technical Help']);

        $response = $this->get('/fr/faq?'.http_build_query(['q' => 'unmatched', 'topic' => 'Getting Started']));

        $response->assertSeeText(__('No questions match your search.'))->assertSeeText(__('Technical Help'));
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(url('/fr/faq?topic=Getting+Started#faq-results'), $xpath->evaluate('string(//div[@class="faq-resets"]/a[1]/@href)'));
        $this->assertSame(url('/fr/faq?q=unmatched#faq-results'), $xpath->evaluate('string(//div[@class="faq-resets"]/a[2]/@href)'));
        $this->assertSame(url('/fr/faq#faq-results'), $xpath->evaluate('string(//div[@class="faq-resets"]/a[3]/@href)'));
        $this->assertSame(url('/fr/faq#faq-results'), $xpath->evaluate('string(//form/@action)'));
        $this->assertSame(3, $xpath->query('//div[@class="faq-resets"]/a')->length);
        $this->assertSame(0, $xpath->query('//div[@class="faq-empty"]//a')->length);
    }

    #[TestWith(['needle', '', 'Clear Search'])]
    #[TestWith(['', 'Getting Started', 'All Questions'])]
    public function test_a_single_filter_offers_only_one_result_reset(string $search, string $topic, string $label): void
    {
        $this->faq(['question' => 'Needle question', 'topic' => 'Getting Started']);

        $response = $this->get('/faq?'.http_build_query(['q' => $search, 'topic' => $topic]));

        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//div[@class="faq-resets"]/a')->length);
        $this->assertSame($label, $xpath->evaluate('string(//div[@class="faq-resets"]/a)'));
        $this->assertSame(url('/faq#faq-results'), $xpath->evaluate('string(//div[@class="faq-resets"]/a/@href)'));
        $response->assertDontSeeText('Clear Search & Filters');
    }

    #[TestWith(['Needle question', 'Unrelated answer.', false])]
    #[TestWith(['Another question', 'A needle in this answer.', true])]
    #[TestWith(['Needle question', 'A needle in this answer too.', false])]
    public function test_only_answer_matches_receive_a_collapsed_result_cue(string $question, string $answer, bool $hasCue): void
    {
        $this->faq(['question' => $question, 'answer' => $answer]);

        $response = $this->get('/faq?q=NEEDLE');

        $xpath = $this->xpath($response->getContent());
        $this->assertSame($hasCue ? 1 : 0, $xpath->query('//summary//span[@class="faq-answer-match"]')->length);
        $this->assertSame(0, $xpath->query('//details[@open]')->length);
    }

    public function test_answer_match_cues_use_editable_copy_and_keep_that_copy_escaped(): void
    {
        $this->faq(['question' => 'A different question', 'answer' => 'A needle in the answer.']);
        $label = '<img src=x onerror=alert(1)> Found in the answer';
        WebsiteCopy::create(['page' => 'faq', 'locale' => 'en', 'published_content' => ['ui__'.sha1('Match found in the answer.') => $label]]);

        $response = $this->get('/faq?q=needle');

        $response->assertSee($label)->assertDontSee($label, false)->assertDontSeeText(__('Match found in the answer.'));
        $this->assertArrayHasKey('ui__'.sha1('Match found in the answer.'), app(WebsiteCopyCatalog::class)->fields('faq'));
    }

    public function test_empty_topic_and_empty_publication_have_distinct_recovery_messages(): void
    {
        $this->get('/faq')->assertSeeText('There are no published questions yet.');
        $this->faq(['topic' => 'Getting Started']);

        $response = $this->get('/faq?topic=Retired');

        $response->assertSeeText('There are no published questions in this category yet.')->assertSeeText('All Questions')->assertDontSeeText('Clear Search & Filters');
        $this->assertSame('Retired', $this->xpath($response->getContent())->evaluate('string(//select/option[@selected]/@value)'));
        $this->get('/faq?topic=Retired&q=anything')->assertSeeText('There are no published questions in this category yet.')->assertDontSeeText(__('No questions match your search.'));
    }

    public function test_whitespace_search_is_empty_and_topic_only_questions_start_closed(): void
    {
        $this->faq(['topic' => 'Getting Started']);

        $response = $this->get('/faq?'.http_build_query(['q' => '   ', 'topic' => 'Getting Started']));

        $response->assertSeeText('1 question');
        $this->assertSame(0, $this->xpath($response->getContent())->query('//details[@open]')->length);
    }

    #[TestWith(['q', ['invalid'], 'Enter a search of up to 200 characters.'])]
    #[TestWith(['topic', ['invalid'], 'Choose a topic of up to 100 characters.'])]
    public function test_array_filters_show_a_recoverable_validation_message(string $field, array $input, string $message): void
    {
        $this->get('/faq?'.http_build_query([$field => $input]))->assertOk()->assertSeeText($message)->assertSeeText('Clear Search & Filters');
    }

    #[TestWith(['q', 201, 'Enter a search of up to 200 characters.'])]
    #[TestWith(['topic', 101, 'Choose a topic of up to 100 characters.'])]
    public function test_oversized_filters_do_not_redirect_in_a_loop_or_raise_server_errors(string $field, int $length, string $message): void
    {
        $this->get('/faq?'.http_build_query([$field => str_repeat('a', $length)]))->assertOk()->assertSeeText($message);
    }

    public function test_answers_and_query_context_stay_escaped_with_line_breaks_preserved(): void
    {
        $answer = "<script>alert(1)</script>\nA meaningful line break.\n\nSecond paragraph.";
        $this->faq(['question' => 'Unsafe <script>question</script>', 'answer' => $answer]);

        $response = $this->get('/faq?'.http_build_query(['q' => '<script>']));

        $response->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<script>question</script>', false)->assertSeeText('Second paragraph.');
        $paragraphs = $this->xpath($response->getContent())->query('//div[@class="faq-answer"]/p');
        $this->assertSame($answer, implode("\n\n", array_map(fn ($paragraph): string => $paragraph->textContent, iterator_to_array($paragraphs))));
    }

    public function test_preview_shows_inactive_drafts_without_published_english_translation_masking_them(): void
    {
        $faq = $this->faq(['question' => 'Public original', 'active' => false]);
        $faq->translations()->create(['locale' => 'en', 'status' => 'published', 'question' => 'Published translation', 'answer' => 'Published answer.']);
        $faq->revisions()->create(['revision_number' => 1, 'title' => 'Private draft question', 'content' => ['answer' => 'Private draft answer.', 'active' => false], 'status' => 'draft']);

        $this->get('/faq')->assertDontSeeText('Private draft')->assertDontSeeText('Published translation');
        $this->get('/faq/preview')->assertForbidden();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))->get('/faq/preview')->assertOk()->assertSeeText('Private draft question')->assertSeeText('Private draft answer.')->assertDontSeeText('Published translation')->assertDontSee('class="faq-search"', false);
    }

    public function test_translation_publication_changes_public_search_but_drafts_do_not(): void
    {
        $faq = $this->faq();
        $translations = app(TranslationService::class);
        $translations->saveDraft($faq, 'fr', ['question' => 'Nouvelle question', 'answer' => 'Réponse en français.']);
        $this->get('/fr/faq?q=Nouvelle')->assertSeeText(__('No questions match your search.'));
        $translations->publishTranslation($faq, 'fr');

        $this->get('/fr/faq?q=Nouvelle')->assertSeeText('Nouvelle question')->assertSeeText('Réponse en français.');
    }

    public function test_published_cms_edits_update_public_content_topic_and_order_while_drafts_remain_private(): void
    {
        $faq = $this->faq(['question' => 'Original CMS question', 'topic' => 'Technical Help']);
        $this->faq(['question' => 'Earlier published question', 'topic' => 'Getting Started', 'sort_order' => 1]);
        $data = ['question' => 'Revised CMS question', 'answer' => 'Revised complete answer.', 'topic' => 'Getting Started', 'sort_order' => 2, 'active' => 1, 'featured' => 1];
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));
        $this->put(route('admin.content.faq.update', $faq), $data + ['action' => 'draft'])->assertSessionHasNoErrors();
        $this->get('/faq')->assertSeeText('Original CMS question')->assertDontSeeText('Revised CMS question');

        $this->put(route('admin.content.faq.update', $faq), $data + ['action' => 'publish'])->assertSessionHasNoErrors();

        $this->get('/faq?'.http_build_query(['topic' => 'Getting Started']))->assertSeeInOrder(['Earlier published question', 'Revised CMS question'])->assertSeeText('Revised complete answer.')->assertDontSeeText('Original CMS question');
        $this->assertTrue($faq->fresh()->featured);
        $this->assertSame('Getting Started', $faq->fresh()->topic);
    }

    public function test_assistants_cannot_view_faq_drafts(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']))->get('/faq/preview')->assertForbidden();
    }

    #[TestWith(['q', 200])]
    #[TestWith(['topic', 100])]
    public function test_filters_at_the_existing_length_limit_are_preserved(string $field, int $length): void
    {
        $value = str_repeat('a', $length);

        $response = $this->get('/faq?'.http_build_query([$field => $value]));

        $response->assertOk()->assertDontSee('aria-invalid="true"', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame($value, $field === 'q' ? $xpath->evaluate('string(//input[@name="q"]/@value)') : $xpath->evaluate('string(//select/option[@selected]/@value)'));
    }

    public function test_translation_queries_are_batched_for_a_collection(): void
    {
        for ($index = 0; $index < 12; $index++) {
            $faq = $this->faq(['question' => 'Question '.$index]);
            $faq->translations()->create(['locale' => 'fr', 'status' => 'published', 'question' => 'Question française '.$index, 'answer' => 'Réponse.']);
        }
        DB::enableQueryLog();

        $response = $this->get('/fr/faq?q=reponse')->assertSeeText('12 questions');

        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `faq_translations`'));
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame(12, $this->xpath($response->getContent())->query('//span[@class="faq-answer-match"]')->length);
    }

    public function test_intro_ui_labels_and_contextual_destinations_use_existing_copy_and_routes(): void
    {
        foreach (['Diagnostic & Booking', 'Pricing & Packages', 'Cancellations & Refunds', 'Student Portal', 'Resources & Games'] as $topic) {
            $this->faq(['topic' => $topic]);
        }
        WebsiteCopy::create(['page' => 'faq', 'locale' => 'en', 'published_content' => ['faq_intro__title' => 'Edited FAQ introduction', 'ui__'.sha1('Search questions') => 'Find an answer']]);

        $response = $this->get('/faq');

        $response->assertSeeText('Edited FAQ introduction')->assertSeeText('Find an answer');
        foreach (['/booking', '/pricing', '/booking-policy', '/student/login', '/resources', '/games'] as $path) {
            $response->assertSee('href="'.url($path).'"', false);
        }
        $this->assertArrayHasKey('ui__'.sha1('Clear Search'), app(WebsiteCopyCatalog::class)->fields('faq'));
        $this->assertSame(route('student.login'), $this->xpath($response->getContent())->evaluate('string(//a[contains(., "Open the Student Portal")]/@href)'));
    }

    /** @param array<string, mixed> $attributes */
    private function faq(array $attributes = []): Faq
    {
        return Faq::create(array_replace(['question' => 'A public question?', 'answer' => 'A public answer.', 'active' => true, 'sort_order' => 0], $attributes));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}
