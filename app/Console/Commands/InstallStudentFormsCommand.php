<?php

namespace App\Console\Commands;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Services\FormBuilderService;
use Illuminate\Console\Command;

class InstallStudentFormsCommand extends Command
{
    protected $signature = 'forms:install-onboarding {--administrator= : Author administrator ID}';

    protected $description = 'Install initial Short and Long Forms without replacing existing published forms';

    public function handle(FormBuilderService $builder, BookingService $bookings): int
    {
        $author = Administrator::query()->whereIn('role', ['super_admin', 'admin'])->when($this->option('administrator'), fn ($query) => $query->whereKey($this->option('administrator')))->orderBy('id')->first();
        if (! $author) {
            $this->error('An existing administrator is required to author onboarding forms.');

            return self::FAILURE;
        }
        $definitions = [
            'short-form' => ['title' => 'Short Form — Your Learning Goals', 'triggers' => ['pre_booking'], 'questions' => [
                ['question_key' => 'goals', 'label' => 'What would you like to achieve with Egyptian Arabic?'],
                ['question_key' => 'experience', 'label' => 'What Arabic learning experience do you have?'],
                ['question_key' => 'context', 'label' => 'Where would you like to use Egyptian Arabic?'],
            ]],
            'long-form' => ['title' => 'Long Form — Learning Profile', 'triggers' => ['after_booking', 'next_session_check'], 'questions' => [
                ['question_key' => 'motivation', 'label' => 'Tell us more about your motivation.'],
                ['question_key' => 'strengths', 'label' => 'What feels comfortable already?'],
                ['question_key' => 'challenges', 'label' => 'What feels most challenging?'],
                ['question_key' => 'practice', 'label' => 'How much practice time can you set aside?'],
                ['question_key' => 'interests', 'label' => 'Which topics interest you?'],
                ['question_key' => 'preferences', 'label' => 'How do you prefer to learn?'],
            ]],
        ];
        foreach ($definitions as $slug => $definition) {
            if (Form::where('slug', $slug)->exists() || ($slug === 'short-form' && $bookings->publishedIntakeForm())) {
                $this->line('Preserved existing '.$slug.'.');

                continue;
            }
            $questions = array_map(fn (array $question): array => [...$question, 'question_type' => 'long_text', 'is_required' => $slug === 'short-form', 'assistant_visible' => true, 'validation_rules' => ['max_length' => 2000]], $definition['questions']);
            $form = $builder->create(['title' => $definition['title'], 'slug' => $slug, 'description' => $slug === 'short-form' ? 'Complete this brief intake before your first lesson.' : 'Your progress saves automatically. Return whenever you are ready.', 'is_mandatory' => $slug === 'short-form', 'can_edit_after_submission' => false, 'triggers' => $definition['triggers']], $questions, $author);
            $builder->publish($form->id, $form->active_version_id, $form->lock_version);
            $this->info('Published '.$slug.'.');
        }

        return self::SUCCESS;
    }
}
