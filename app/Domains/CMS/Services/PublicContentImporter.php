<?php

namespace App\Domains\CMS\Services;

use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormQuestion;
use App\Domains\Forms\Models\FormVersion;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class PublicContentImporter
{
    public function __construct(private TranslationService $translations, private ContentService $pages, private FormBuilderService $forms) {}

    /** @return array{changed: bool, faqs: int, hidden_session_types: int, hidden_resources: int} */
    public function import(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('This reviewed content import is restricted to local and testing environments.');
        }

        return DB::transaction(function (): array {
            $version = (string) config('public_content.version');
            if (Setting::get('approved_public_content.version') === $version) {
                return ['changed' => $this->importHomeControls($version), 'faqs' => 0, 'hidden_session_types' => 0, 'hidden_resources' => 0];
            }

            $settings = [
                'site_name' => config('public_content.site_name'),
                'site_footer_text' => config('public_content.footer'),
                'hero_title' => config('public_content.home.0.title'),
                'hero_subtitle' => config('public_content.home.0.blocks.0.text'),
                'about_biography' => collect(config('public_content.about.0.blocks'))->pluck('text')->implode("\n\n"),
                'about_philosophy' => collect(config('public_content.about.2.blocks'))->pluck('text')->implode("\n\n"),
                'counters.live_users.public_enabled' => '0',
                'counters.monthly_traffic.public_enabled' => '0',
                'counters.learning_hours.public_enabled' => '0',
            ];
            foreach ($settings as $key => $value) {
                $existing = Setting::query()->where('key', $key)->first();
                if ($existing) {
                    $this->preserve($existing);
                }
                Setting::set($key, $value, 'public_content', true);
            }

            $this->importHomeControls($version);

            $ids = [];
            foreach (config('public_content.faqs') as $index => $source) {
                $faq = Faq::query()->firstOrNew(['question' => $source['question']]);
                if ($faq->exists) {
                    $this->preserve($faq);
                }
                $faq->fill([
                    'answer' => $source['answer'], 'topic' => $source['topic'], 'featured' => $source['featured'],
                    'active' => true, 'sort_order' => $index + 1,
                ])->save();
                $this->translations->updateEnglishSource($faq, ['question' => $faq->question, 'answer' => $faq->answer]);
                $ids[] = $faq->id;
            }
            foreach (Faq::query()->where('active', true)->whereNotIn('id', $ids)->get() as $previous) {
                $this->preserve($previous);
                $previous->update(['active' => false]);
            }

            $hiddenSessions = SessionType::query()->whereIn('slug', ['feature-browser-qa', 'pass4-two-hour-qa'])
                ->where('public_bookable', true)->update(['public_bookable' => false]);
            $hiddenResources = Resource::query()->where('slug', 'synthetic-followup-access-guide')
                ->where('public_catalogue', true)->update(['public_catalogue' => false]);

            foreach (Game::query()->where('target_url', 'https://mohamedateff.com/6word')->get() as $game) {
                $this->preserve($game);
                $game->update(['title' => '6-Word Story', 'description' => config('public_content.game_feature.description'), 'badge' => 'Speaking & Storytelling']);
                $this->translations->updateEnglishSource($game, ['title' => $game->title, 'description' => $game->description, 'badge' => $game->badge]);
            }

            $this->updateTerms();
            $this->updateShortForm();
            Setting::set('approved_public_content.version', $version, 'public_content');
            Setting::set('approved_public_content.faq_ids', $ids, 'public_content');

            return ['changed' => true, 'faqs' => count($ids), 'hidden_session_types' => $hiddenSessions, 'hidden_resources' => $hiddenResources];
        });
    }

    private function importHomeControls(string $version): bool
    {
        $controlsVersion = $version.'.home-controls.1';
        if (Setting::get('approved_public_content.home_controls_version') === $controlsVersion) {
            return false;
        }
        foreach (config('public_content.home_controls') as $index => $controls) {
            foreach ($controls as $field => $key) {
                $section = config('public_content.home.'.$index);
                $value = $field === 'intro' ? $section['blocks'][0]['text'] : $section[$field];
                $existing = Setting::query()->where('key', $key)->first();
                if ($existing && $existing->value !== $value) {
                    $this->preserve($existing);
                }
                Setting::set($key, $value, 'homepage', true);
            }
        }
        $button = Setting::query()->where('key', 'home_cta_button')->first();
        if ($button && $button->value !== 'Book Your Diagnostic') {
            $this->preserve($button);
        }
        Setting::set('home_cta_button', 'Book Your Diagnostic', 'homepage', true);
        Setting::set('approved_public_content.home_controls_version', $controlsVersion, 'public_content');

        return true;
    }

    private function preserve(Model $entity): void
    {
        $query = ContentRevision::query()->where('revisable_type', $entity->getMorphClass())->where('revisable_id', $entity->getKey());
        ContentRevision::create([
            'revisable_type' => $entity->getMorphClass(), 'revisable_id' => $entity->getKey(),
            'revision_number' => ((int) $query->max('revision_number')) + 1,
            'title' => $entity->getAttribute('question') ?? $entity->getAttribute('key') ?? $entity->getAttribute('title'),
            'content' => $entity->toArray(), 'status' => 'archived',
        ]);
    }

    private function updateTerms(): void
    {
        $terms = Page::query()->where('slug', 'terms')->first();
        if (! $terms) {
            return;
        }
        $content = (string) $terms->content;
        $parts = preg_split('/(?=^[1-5]\. )/m', $content);
        if (! is_array($parts) || count($parts) !== 6
            || ! str_starts_with($parts[1], '1. Delivery & Platform Logistics')
            || ! str_starts_with($parts[5], '5. Materials & Usage')) {
            throw new LogicException('The Terms structure differs from the reviewed source. Review it before applying a targeted policy replacement.');
        }
        $delivery = preg_replace('/Package Validity:.*$/s', '', $parts[1]);
        $payment = "2. Payment & Booking\nAppointments are confirmed when the final booking step succeeds. Payment is arranged manually and separately with Abdallah. There is no automated checkout.\n\n";
        $policy = config('public_content.policy.title')."\n".config('public_content.policy.version')."\n\n"
            .collect(config('public_content.policy.blocks'))->pluck('text')->implode("\n\n")."\n\n";
        $this->preserve($terms);
        $this->pages->updateEnglishSource($terms, ['content' => trim($parts[0].$delivery.$payment.$policy.$parts[5])]);
    }

    private function updateShortForm(): void
    {
        $form = Form::query()->where('status', 'published')->whereHas('triggers', fn ($query) => $query->where('trigger_name', 'pre_booking'))
            ->with('publishedVersion.questions.options')->first();
        if (! $form?->publishedVersion || (int) $form->active_version_id !== (int) $form->published_version_id) {
            return;
        }
        $copy = [
            'goals' => ['What would you like to achieve with Arabic?', "Tell me what brings you to the language and what you'd like to work toward.", "For example, I'd like to communicate more comfortably with people in Egypt..."],
            'experience' => ['Have you studied or used Arabic before?', "It's completely fine if you're starting from zero. If you've studied Arabic or grown up hearing it, tell me a little about your experience.", 'Tell me about your previous experience, if any...'],
            'context' => ['Where would you like to use your Arabic?', 'For example, everyday conversations, family connections, university, work, travel, or personal interests.', 'Tell me where Arabic would be useful in your life...'],
        ];
        /** @var FormVersion $publishedVersion */
        $publishedVersion = $form->publishedVersion;
        /** @var Collection<int, FormQuestion> $sourceQuestions */
        $sourceQuestions = $publishedVersion->getRelation('questions');
        $questions = $sourceQuestions->map(function (FormQuestion $question) use ($copy): array {
            $data = $question->only(['question_key', 'label', 'description', 'question_type', 'is_required', 'assistant_visible', 'sort_order', 'validation_rules', 'presentation_config', 'conditional_logic']);
            $data['options'] = $question->options->map(fn ($option): array => $option->only(['label', 'value', 'sort_order']))->all();
            if (isset($copy[$question->question_key])) {
                [$data['label'], $data['description'], $placeholder] = $copy[$question->question_key];
                $data['presentation_config'] = array_merge($data['presentation_config'] ?? [], ['placeholder' => $placeholder]);
            }

            return $data;
        })->all();
        $draft = $this->forms->update($form->id, ['changelog' => 'Approved Website Copy 2.0: Arabic intake labels and helpers; stable keys and requirements preserved.'], $questions, $form->active_version_id, $form->lock_version);
        $this->forms->publish($draft->id, $draft->active_version_id, $draft->lock_version);
    }
}
