<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormVersion;
use App\Domains\Forms\Services\BookingIntakeService;
use App\Domains\Forms\Services\FinalStudentFormsInstaller;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Forms\Services\FormSubmissionService;
use App\Domains\Forms\Services\FormValidationService;
use App\Domains\Resources\Services\EmailQualityService;
use App\Domains\Students\Models\Student;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\IssuesBookingSlotIds;
use Tests\TestCase;

class FinalStudentFormsTest extends TestCase
{
    use IssuesBookingSlotIds;
    use RefreshDatabase;

    public function test_fresh_installation_publishes_exact_final_contract_and_second_run_does_nothing(): void
    {
        $author = Administrator::factory()->create();
        $this->artisan('forms:install-onboarding', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Form::count());
        $this->artisan('forms:install-onboarding', ['--administrator' => $author->id])->assertSuccessful();

        foreach (['short-form' => 4, 'long-form' => 21] as $slug => $count) {
            $form = Form::where('slug', $slug)->with('publishedVersion.questions.options', 'triggers')->firstOrFail();
            $this->assertCount($count, $form->publishedVersion->questions);
            $this->assertSame($slug === 'short-form', $form->is_mandatory);
            $this->assertFalse($form->can_edit_after_submission);
            $this->assertSame($slug === 'short-form' ? ['pre_booking'] : ['after_booking'], $form->triggers->pluck('trigger_name')->all());
            foreach ($form->publishedVersion->questions as $index => $question) {
                $expected = config('student_forms.'.$slug.'.questions.'.$index);
                foreach (array_diff(array_keys($expected), ['options']) as $attribute) {
                    $this->assertSame($expected[$attribute], $question->getAttribute($attribute), $question->question_key.'.'.$attribute);
                }
                $this->assertSame($expected['options'], $question->options->map(fn ($option): array => ['label' => $option->label, 'value' => $option->value, 'sort_order' => (int) $option->sort_order])->all());
                $this->assertSame($slug === 'short-form', $question->is_required);
                $this->assertSame($slug === 'short-form', $question->assistant_visible);
                foreach ($question->options as $index => $option) {
                    $this->assertSame(sprintf('option_%02d', $index + 1), $option->value);
                }
            }
        }
        $profile = Form::where('slug', 'long-form')->firstOrFail()->publishedVersion->questions;
        $this->assertSame(['awa_pro_01', 'awa_pro_x01', 'awa_pro_x02', 'awa_pro_02', 'awa_pro_03', 'awa_pro_04', 'awa_pro_05', 'awa_pro_06', 'awa_pro_07', 'awa_pro_08', 'awa_pro_09', 'awa_pro_x03', 'awa_pro_10', 'awa_pro_11', 'awa_pro_12', 'awa_pro_x04', 'awa_pro_x05', 'awa_pro_13', 'awa_pro_x06', 'awa_pro_14', 'awa_pro_15'], $profile->pluck('question_key')->all());
        $this->assertSame(11, $profile->where('question_type', 'single_choice')->count());
        $this->assertSame(10, $profile->where('question_type', 'long_text')->count());
        $this->assertSame(6, $profile->pluck('presentation_config.section.key')->unique()->count());
        $before = app(FinalStudentFormsInstaller::class)->snapshot();

        $this->artisan('forms:install-onboarding', ['--replace-final' => true])->assertSuccessful();

        $this->assertSame($before, app(FinalStudentFormsInstaller::class)->snapshot());
        $this->assertSame(2, FormVersion::count());
    }

    public function test_replacement_preserves_all_history_associations_creator_and_unpublished_work(): void
    {
        $creator = Administrator::factory()->create();
        $author = Administrator::factory()->create(['role' => 'admin']);
        $builder = app(FormBuilderService::class);
        $old = $builder->create(['slug' => 'long-form', 'title' => 'Old profile', 'triggers' => ['after_booking', 'next_session_check']], [['question_key' => 'legacy', 'label' => 'Old question', 'question_type' => 'long_text', 'assistant_visible' => true]], $creator);
        $old = $builder->publish($old->id, $old->active_version_id, $old->lock_version);
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        $submission = app(FormSubmissionService::class)->save($student, $old, ['legacy' => 'A historical response'], true);
        $submission->update(['booking_id' => $booking->id, 'contact_id' => $booking->contact_id]);
        $draft = $builder->update($old->id, ['title' => 'Unpublished work'], [['question_key' => 'draft', 'label' => 'Keep this draft', 'question_type' => 'short_text']], $old->active_version_id, $old->lock_version);
        $oldVersions = $old->versions()->with('questions.options')->get()->toArray();
        $integrity = app(FinalStudentFormsInstaller::class)->integrity();

        $this->artisan('forms:install-onboarding', ['--administrator' => $author->id])->assertSuccessful();
        $this->assertSame('Unpublished work', $old->fresh()->title);
        $this->artisan('forms:install-onboarding', ['--replace-final' => true, '--administrator' => $author->id])->assertSuccessful();

        $final = $old->fresh();
        $this->assertSame($creator->id, $final->created_by);
        $this->assertSame(3, $final->publishedVersion->version_number);
        $this->assertSame($oldVersions, $old->versions()->whereIn('id', array_column($oldVersions, 'id'))->with('questions.options')->get()->toArray());
        $this->assertSame($integrity, app(FinalStudentFormsInstaller::class)->integrity());
        $this->assertNotSame($draft->active_version_id, $final->active_version_id);
        $this->assertSame(21, $final->publishedVersion->questions()->count());
        $this->asStudent($student);
        $this->get(route('student.forms.show', 'long-form'))->assertSee('Historical questionnaire')->assertSee('A historical response')->assertSee('Start Updated Questionnaire');
        $this->get(route('student.forms.show', ['long-form', 'new_version' => 1]))->assertSee('A learning experience made for you')->assertDontSee('A historical response');
    }

    public function test_invalid_author_and_competing_intake_fail_without_partial_installation(): void
    {
        $assistant = Administrator::factory()->create(['role' => 'assistant']);
        $this->artisan('forms:install-onboarding', ['--administrator' => $assistant->id, '--replace-final' => true])->assertFailed();
        $this->assertSame(0, Form::count());
        $author = Administrator::factory()->create();
        $builder = app(FormBuilderService::class);
        $other = $builder->create(['slug' => 'custom-intake', 'title' => 'Other', 'triggers' => ['pre_booking']], [['question_key' => 'other', 'label' => 'Other', 'question_type' => 'short_text']], $author);
        $builder->publish($other->id, $other->active_version_id, $other->lock_version);

        $this->artisan('forms:install-onboarding', ['--replace-final' => true])->assertFailed();

        $this->assertSame(1, Form::count());
        $this->assertDatabaseHas('form_triggers', ['form_id' => $other->id, 'trigger_name' => 'pre_booking']);
    }

    public function test_production_and_remote_configuration_are_refused_before_writing(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('forms:install-onboarding', ['--replace-final' => true])->assertFailed();
        $this->app->instance('env', 'testing');
        $connection = DB::connection();
        $original = $connection->getConfig();
        config(['database.connections.unsafe' => [...$original, 'host' => 'remote.invalid'], 'database.default' => 'unsafe']);
        try {
            $this->artisan('forms:install-onboarding', ['--replace-final' => true])->assertFailed();
        } finally {
            config(['database.default' => 'mysql']);
        }
        $this->assertSame(0, Form::count());
    }

    public function test_stale_inspection_is_refused_and_mid_publication_failure_rolls_back_both_forms(): void
    {
        $author = Administrator::factory()->create();
        $installer = app(FinalStudentFormsInstaller::class);
        $stale = $installer->plan(true);
        $this->install();
        try {
            $installer->apply($author, true, $stale);
            $this->fail('A stale plan must conflict.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('changed after inspection', $exception->getMessage());
        }
        $before = $installer->snapshot();
        config(['student_forms.short-form.metadata.title' => 'Changed short', 'student_forms.long-form.metadata.title' => 'Changed long']);
        $count = 0;
        Form::updating(function () use (&$count): void {
            if (++$count === 3) {
                throw new RuntimeException('Simulated publication failure');
            }
        });
        try {
            $this->artisan('forms:install-onboarding', ['--replace-final' => true])->assertFailed();
        } finally {
            Form::flushEventListeners();
        }
        $this->assertSame($before, $installer->snapshot());
    }

    #[DataProvider('conditionalCases')]
    public function test_each_conditional_branch_uses_stable_values(string $followup, string $parent, array $triggers): void
    {
        $this->install();
        $questions = Form::where('slug', 'long-form')->firstOrFail()->publishedVersion->questions()->with('options')->get()->all();
        foreach (array_merge(['', 'option_07'], $triggers) as $value) {
            $visible = collect(app(FormValidationService::class)->visibleQuestions($questions, [$parent => $value]))->pluck('question_key')->all();
            $this->assertSame(in_array($value, $triggers, true), in_array($followup, $visible, true));
        }
    }

    public static function conditionalCases(): array
    {
        return [
            ['awa_pro_x01', 'awa_pro_01', ['option_03']],
            ['awa_pro_x02', 'awa_pro_01', ['option_02']],
            ['awa_pro_x03', 'awa_pro_09', ['option_02', 'option_03', 'option_04']],
            ['awa_pro_x04', 'awa_pro_12', ['option_02', 'option_03']],
            ['awa_pro_x05', 'awa_pro_12', ['option_01', 'option_03']],
            ['awa_pro_x06', 'awa_pro_13', ['option_02', 'option_03']],
        ];
    }

    public function test_maximum_visible_questions_and_parent_changes_purge_only_current_answers(): void
    {
        $this->install();
        $form = Form::where('slug', 'long-form')->firstOrFail();
        $student = Student::factory()->verified()->create();
        Booking::factory()->create(['student_id' => $student->id]);
        $parents = ['awa_pro_01' => 'option_03', 'awa_pro_09' => 'option_02', 'awa_pro_12' => 'option_03', 'awa_pro_13' => 'option_03'];
        $visible = app(FormValidationService::class)->visibleQuestions($form->publishedVersion->questions()->with('options')->get()->all(), $parents);
        $this->assertCount(20, $visible);
        $this->asStudent($student);
        $this->postJson(route('student.forms.autosave', $form->slug), ['form_version_id' => $form->published_version_id, 'answers' => $parents + ['awa_pro_x01' => 'option_01', 'awa_pro_x04' => 'Professional example']])->assertOk();
        $submission = FormSubmission::where('student_id', $student->id)->firstOrFail();
        $firstSnapshot = $submission->revisions()->firstOrFail()->snapshot_answers;
        $this->post(route('student.forms.save', $form->slug), ['intent' => 'draft', 'submission_id' => $submission->id, 'form_version_id' => $form->published_version_id, 'answers' => ['awa_pro_01' => 'option_02', 'awa_pro_12' => 'option_04', 'awa_pro_x01' => 'option_01', 'awa_pro_x04' => 'Professional example']])->assertSessionHas('success', 'Your draft was saved.');
        $this->get(route('student.forms.show', $form->slug))->assertDontSee('Professional example');
        $this->assertSame(['awa_pro_01', 'awa_pro_12'], $submission->fresh()->answers()->join('form_questions', 'form_questions.id', '=', 'form_answers.form_question_id')->orderBy('form_questions.sort_order')->pluck('form_questions.question_key')->all());
        $this->assertSame($firstSnapshot, $submission->revisions()->where('revision_number', 1)->firstOrFail()->snapshot_answers);
        $this->post(route('student.forms.save', $form->slug), ['intent' => 'submit', 'submission_id' => $submission->id, 'answers' => []])->assertSessionHas('success', 'Your responses were submitted.');
        $this->assertSame('submitted', $submission->fresh()->status);
        $this->assertSame(0, $submission->answers()->count());
        $this->get(route('student.forms.show', $form->slug))->assertSee('Your Answers Are Saved.')->assertDontSee('>Submit Answers<', false);
    }

    public function test_final_intake_rejects_blank_invalid_and_stale_inputs(): void
    {
        $this->install();
        $form = Form::where('slug', 'short-form')->firstOrFail();
        $intake = app(BookingIntakeService::class);
        foreach ([[], ['awa_int_01' => 'forged']] as $answers) {
            try {
                $intake->validatePublicIntake($form->published_version_id, $answers);
                $this->fail('Invalid intake must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $answers = array_fill_keys(['awa_int_01', 'awa_int_02', 'awa_int_03', 'awa_int_04'], 'option_01');
        $this->assertSame($answers, $intake->validatePublicIntake($form->published_version_id, $answers));
        $this->expectException(ValidationException::class);
        $intake->validatePublicIntake($form->published_version_id + 999, $answers);
    }

    public function test_all_profile_answers_are_excluded_for_assistants_and_labels_are_readable_for_admins_in_views_and_exports(): void
    {
        $this->install();
        $form = Form::where('slug', 'long-form')->firstOrFail();
        $student = Student::factory()->verified()->create();
        Booking::factory()->create(['student_id' => $student->id]);
        $answers = ['awa_pro_01' => 'option_03', 'awa_pro_09' => 'option_02', 'awa_pro_12' => 'option_03', 'awa_pro_13' => 'option_03'];
        foreach ($form->publishedVersion->questions as $question) {
            $answers[$question->question_key] ??= $question->question_type === 'long_text' ? '=PRIVATE_FORMULA <private-response>' : 'option_01';
        }
        $submission = app(FormSubmissionService::class)->save($student, $form, $answers, true);
        $this->assertSame(20, $submission->answers()->count());
        foreach (['admin', 'super_admin', 'assistant'] as $role) {
            $this->actingAs(Administrator::factory()->create(['role' => $role]), 'web');
            foreach ([route('admin.forms.submissions', $form), route('admin.students.show', $student)] as $url) {
                $response = $this->get($url)->assertOk();
                if ($role === 'assistant') {
                    $response->assertDontSee('PRIVATE_FORMULA')->assertDontSee('Reading Arabic.');
                    $response->assertViewHas($url === route('admin.forms.submissions', $form) ? 'submissions' : 'formSubmissions', fn ($submissions): bool => $submissions->every(fn ($submission): bool => $submission->answers->isEmpty()));
                } else {
                    $response->assertSee('Reading Arabic.')->assertSee('&lt;private-response&gt;', false)->assertDontSee('=PRIVATE_FORMULA <private-response>', false);
                }
            }
            foreach (['csv', 'xlsx'] as $format) {
                $response = $this->get(route('admin.forms.export', [$form, 'format' => $format]))->assertOk();
                if ($format === 'csv') {
                    $content = $response->streamedContent();
                    $rows = array_map('str_getcsv', array_filter(explode("\n", trim($content))));
                } else {
                    $file = $response->baseResponse->getFile()->getPathname();
                    $book = IOFactory::load($file);
                    $rows = $book->getActiveSheet()->toArray();
                    $content = json_encode($rows, JSON_THROW_ON_ERROR);
                    $book->disconnectWorksheets();
                    unlink($file);
                }
                $this->assertCount(count($rows[0]), $rows[1]);
                if ($role === 'assistant') {
                    $this->assertCount(5, $rows[0]);
                    $this->assertStringNotContainsString('PRIVATE_FORMULA', $content);
                    $this->assertStringNotContainsString('option_03', $content);
                } else {
                    $this->assertStringContainsString('Reading Arabic.', $content);
                    $this->assertStringContainsString("'=PRIVATE_FORMULA", $content);
                    $this->assertStringNotContainsString('option_03', $content);
                }
            }
        }
    }

    public function test_admin_edit_round_trip_keeps_sections_and_introduction_metadata(): void
    {
        $author = $this->install();
        $form = Form::where('slug', 'long-form')->firstOrFail();
        $edit = $this->actingAs($author, 'web')->get(route('admin.forms.edit', $form))->assertOk();
        $questions = json_decode($edit->viewData('questionsJson'), true, flags: JSON_THROW_ON_ERROR);
        $this->put(route('admin.forms.update', $form), [...config('student_forms.long-form.metadata'), 'base_version_id' => $form->active_version_id, 'lock_version' => $form->lock_version, 'questions_json' => json_encode($questions, JSON_THROW_ON_ERROR)])->assertRedirect();
        $this->assertSame($questions[0]['presentation_config'], $form->fresh()->activeVersion->questions->first()->presentation_config);
    }

    public function test_optional_written_answers_accept_empty_and_ten_thousand_characters_but_reject_longer_text(): void
    {
        $this->install();
        $form = Form::where('slug', 'long-form')->firstOrFail();
        $questions = $form->publishedVersion->questions()->with('options')->get()->all();
        $validation = app(FormValidationService::class);
        $this->assertSame([], $validation->validateAnswers($questions, []));
        $parents = ['awa_pro_09' => 'option_02', 'awa_pro_12' => 'option_03', 'awa_pro_13' => 'option_03'];
        foreach (collect($questions)->where('question_type', 'long_text') as $question) {
            $answers = $parents + [$question->question_key => str_repeat('a', 10000)];
            $validated = $validation->validateAnswers($questions, $answers);
            ksort($answers);
            ksort($validated);
            $this->assertSame($answers, $validated);
            try {
                $validation->validateAnswers($questions, $parents + [$question->question_key => str_repeat('a', 10001)]);
                $this->fail('The written-response limit must be enforced.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('answers.'.$question->question_key, $exception->errors());
            }
        }
    }

    public function test_choice_display_uses_its_historical_options_and_preserves_legacy_values(): void
    {
        $author = Administrator::factory()->create();
        $builder = app(FormBuilderService::class);
        $question = ['question_key' => 'choice', 'label' => 'Choice', 'question_type' => 'single_choice', 'options' => [['value' => 'option_01', 'label' => 'Historical label']]];
        $form = $builder->create(['slug' => 'historical-labels', 'title' => 'History'], [$question], $author);
        $form = $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        $oldQuestion = $form->publishedVersion->questions()->with('options')->firstOrFail();
        $question['options'][0]['label'] = 'Current label';
        $updated = $builder->update($form->id, [], [$question], $form->active_version_id, $form->lock_version);
        $builder->publish($updated->id, $updated->active_version_id, $updated->lock_version);
        $this->assertSame('Historical label', $oldQuestion->displayAnswer('option_01'));
        $this->assertSame('Current label', $updated->activeVersion->questions()->with('options')->firstOrFail()->displayAnswer('option_01'));
        $this->assertSame('Legacy free-text choice', $oldQuestion->displayAnswer('Legacy free-text choice'));
        $this->assertSame('', $oldQuestion->displayAnswer(null));
    }

    public function test_publication_while_booking_is_open_recovers_to_the_new_intake_without_copying_answers(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00', 'UTC'));
        $this->partialMock(EmailQualityService::class, fn ($mock) => $mock->shouldReceive('dnsRecords')->andReturn([['type' => 'MX', 'target' => 'mail.fixture.test']]));
        $author = $this->install();
        $form = Form::where('slug', 'short-form')->firstOrFail();
        $session = SessionType::factory()->create(['slug' => 'diagnostic-session', 'public_bookable' => true]);
        AvailabilityRule::create(['weekday' => 2, 'start_time' => '00:00', 'end_time' => '23:59', 'buffer_minutes' => 0, 'enabled' => true]);
        $component = Livewire::test(BookingWizard::class)->call('selectTimezone', 'Europe/Paris')->call('selectDate', '2026-10-27');
        $component->call('selectSlot', $this->slotIdFor($session, '2026-10-27 12:00:00', 'Europe/Paris', $component->get('visitorToken')))
            ->set('first_name', 'Synthetic')->set('last_name', 'Learner')->set('date_of_birth', '1990-01-15')->set('email', 'synthetic.learner@example.com')->set('phone', '+1 202 555 0123');
        $answers = $form->publishedVersion->questions->mapWithKeys(fn ($question): array => [$question->id => 'option_01'])->all();
        $component->set('intakeAnswers', $answers);
        $builder = app(FormBuilderService::class);
        $updated = $builder->update($form->id, config('student_forms.short-form.metadata'), config('student_forms.short-form.questions'), $form->active_version_id, $form->lock_version);
        $updated = $builder->publish($updated->id, $updated->active_version_id, $updated->lock_version);

        $component->call('submitDetails')->assertSet('currentStep', 3)->assertSee('The intake form was updated while you were booking. Please review the updated questions.')->assertSet('initialPublishedVersionId', $updated->published_version_id);

        $this->assertSame([], $component->get('intakeAnswers'));
        $this->assertSame(0, Booking::count());
        $newAnswers = $updated->publishedVersion->questions->mapWithKeys(fn ($question): array => [$question->id => 'option_01'])->all();
        $component->set('intakeAnswers', $newAnswers)->call('submitDetails')->assertHasNoErrors()->assertSet('currentStep', 4)->assertSee('Speak more comfortably and naturally.');
    }

    private function install(): Administrator
    {
        $author = Administrator::factory()->create();
        $installer = app(FinalStudentFormsInstaller::class);
        $installer->apply($author, true, $installer->plan(true));

        return $author;
    }

    private function asStudent(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String()]);
    }
}
