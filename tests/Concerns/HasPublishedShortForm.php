<?php

namespace Tests\Concerns;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Services\FormBuilderService;
use Database\Factories\AdministratorFactory;
use Illuminate\Support\Str;

trait HasPublishedShortForm
{
    /** Scheduling tests use an intake with optional answers; required onboarding is tested separately. */
    protected function installShortFormFixture(): Form
    {
        $existing = app(BookingService::class)->publishedIntakeForm();
        if ($existing) {
            return $existing;
        }
        $author = Administrator::first() ?? AdministratorFactory::new()->create(['role' => 'assistant']);
        $builder = app(FormBuilderService::class);
        $form = $builder->create(['title' => 'Short Form fixture', 'slug' => 'short-fixture-'.Str::lower(Str::random(12)), 'triggers' => ['pre_booking'], 'is_mandatory' => true], [
            ['question_key' => 'goals', 'label' => 'Goals', 'question_type' => 'short_text'],
            ['question_key' => 'experience', 'label' => 'Experience', 'question_type' => 'short_text'],
            ['question_key' => 'context', 'label' => 'Context', 'question_type' => 'short_text'],
        ], $author);

        return $builder->publish($form->id, $form->active_version_id, $form->lock_version);
    }
}
