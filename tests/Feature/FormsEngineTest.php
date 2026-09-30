<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormAnswer;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormSubmissionRevision;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Forms\Services\FormValidationService;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FormsEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchecked_form_metadata_checkboxes_are_saved_as_false(): void
    {
        $admin = $this->administrator('admin');
        $form = $this->publishedForm($admin, [
            ['question_key' => 'goal', 'label' => 'Learning goal', 'question_type' => 'short_text', 'is_required' => false, 'assistant_visible' => true],
        ], ['is_mandatory' => true, 'can_edit_after_submission' => true]);
        $version = $form->activeVersion;

        $this->actingAs($admin, 'web')->put(route('admin.forms.update', $form), [
            'title' => $form->title,
            'slug' => $form->slug,
            'trigger' => 'none',
            'base_version_id' => $version->id,
            'lock_version' => $form->lock_version,
            'questions_json' => json_encode([
                ['question_key' => 'goal', 'label' => 'Learning goal', 'question_type' => 'short_text', 'is_required' => false, 'assistant_visible' => true],
            ], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('admin.forms.edit', $form));

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'is_mandatory' => false,
            'can_edit_after_submission' => false,
        ]);
    }

    public function test_conditional_required_answers_are_validated_server_side_and_hidden_values_are_purged(): void
    {
        $admin = $this->administrator('super_admin');
        $form = $this->publishedForm($admin, [
            ['question_key' => 'studied_before', 'label' => 'Have you studied Arabic?', 'question_type' => 'yes_no', 'is_required' => true, 'assistant_visible' => true],
            [
                'question_key' => 'previous_course', 'label' => 'Which course?', 'question_type' => 'short_text',
                'is_required' => true, 'assistant_visible' => false,
                'conditional_logic' => ['logic_version' => 1, 'mode' => 'all', 'conditions' => [['question_key' => 'studied_before', 'operator' => 'equals', 'value' => true]]],
            ],
            [
                'question_key' => 'course_level', 'label' => 'Course level?', 'question_type' => 'short_text',
                'is_required' => true, 'assistant_visible' => true,
                'conditional_logic' => ['logic_version' => 1, 'mode' => 'all', 'conditions' => [['question_key' => 'previous_course', 'operator' => 'is_answered']]],
            ],
        ]);
        $student = Student::factory()->verified()->create();
        $this->asStudent($student);

        $this->get(route('student.forms.show', $form->slug))->assertOk()->assertSee('Have you studied Arabic?');
        $this->post(route('student.forms.save', $form->slug), [
            'intent' => 'submit',
            'answers' => ['studied_before' => '0', 'previous_course' => 'should be removed', 'course_level' => 'must also be purged'],
        ])->assertRedirect(route('student.forms.show', $form->slug));

        $submission = FormSubmission::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('submitted', $submission->status);
        $this->assertSame(1, FormAnswer::query()->where('form_submission_id', $submission->id)->count());
        $this->assertSame(['studied_before' => false], $submission->revisions()->firstOrFail()->snapshot_answers);

        $student2 = Student::factory()->verified()->create();
        $this->asStudent($student2);
        $this->post(route('student.forms.save', $form->slug), [
            'intent' => 'submit',
            'answers' => ['studied_before' => '1'],
        ])->assertSessionHasErrors('answers.previous_course');
        $this->assertDatabaseMissing('form_submissions', ['student_id' => $student2->id]);
    }

    public function test_drafts_freeze_old_versions_and_stale_builder_posts_conflict(): void
    {
        $admin = $this->administrator('super_admin');
        $form = $this->publishedForm($admin, [
            ['question_key' => 'goal', 'label' => 'Learning goal', 'question_type' => 'short_text', 'is_required' => false, 'assistant_visible' => true],
        ]);
        $student = Student::factory()->verified()->create();
        $this->asStudent($student);
        $this->post(route('student.forms.save', $form->slug), ['intent' => 'draft', 'answers' => ['goal' => 'Travel']])->assertRedirect();
        $oldVersion = $form->activeVersion;
        $oldQuestion = $oldVersion->questions()->firstOrFail();

        $updatedQuestions = [[
            'question_key' => 'goal', 'label' => 'Updated learning goal', 'question_type' => 'short_text', 'is_required' => false, 'assistant_visible' => true,
        ]];
        $this->actingAs($admin, 'web')->put(route('admin.forms.update', $form), [
            'title' => $form->title,
            'slug' => $form->slug,
            'trigger' => 'none',
            'base_version_id' => $oldVersion->id,
            'lock_version' => $form->lock_version,
            'questions_json' => json_encode($updatedQuestions, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $form->refresh();
        $this->assertNotSame((int) $oldVersion->id, (int) $form->active_version_id);
        $this->assertSame(2, (int) $form->activeVersion->version_number);
        $this->assertSame((int) $oldVersion->id, (int) $form->published_version_id);
        $this->assertSame('Learning goal', $oldQuestion->fresh()->label);
        $this->assertSame((int) $oldVersion->id, (int) FormSubmission::query()->where('student_id', $student->id)->value('form_version_id'));
        $this->actingAs($admin, 'web')
            ->get(route('admin.forms.submissions', $form))
            ->assertOk()
            ->assertViewHas('versionId', (int) $oldVersion->id)
            ->assertSee('Travel');
        $export = $this->actingAs($admin, 'web')->get(route('admin.forms.export', $form));
        $export->assertOk();
        $this->assertStringContainsString('Travel', $export->streamedContent());
        $this->asStudent($student);
        $this->get(route('student.forms.show', $form->slug))
            ->assertOk()
            ->assertSee('Learning goal')
            ->assertDontSee('Updated learning goal')
            ->assertDontSee('Start updated version');

        $this->actingAs($admin, 'web')->put(route('admin.forms.publish', $form), [
            'base_version_id' => $form->active_version_id,
            'lock_version' => $form->lock_version,
        ])->assertRedirect();

        $form->refresh();
        $this->assertSame((int) $form->active_version_id, (int) $form->published_version_id);
        $this->asStudent($student);
        $this->get(route('student.forms.show', $form->slug))
            ->assertOk()
            ->assertSee('An updated version is available')
            ->assertSee('Start updated version')
            ->assertSee('Learning goal');
        $this->get(route('student.forms.show', [$form->slug, 'new_version' => 1]))
            ->assertOk()
            ->assertSee('Updated learning goal');

        $this->post(route('student.forms.save', $form->slug), [
            'intent' => 'draft',
            'answers' => ['goal' => 'Career development'],
        ])->assertRedirect(route('student.forms.show', $form->slug));
        $newVersionSubmission = FormSubmission::query()
            ->where('student_id', $student->id)
            ->where('form_version_id', $form->published_version_id)
            ->firstOrFail();
        $this->assertSame('Career development', $newVersionSubmission->answers()->firstOrFail()->value_text);
        $this->assertDatabaseHas('form_submissions', [
            'student_id' => $student->id,
            'form_version_id' => $oldVersion->id,
            'status' => 'draft',
        ]);

        $this->actingAs($admin, 'web')->put(route('admin.forms.update', $form), [
            'title' => $form->title,
            'slug' => $form->slug,
            'trigger' => 'none',
            'base_version_id' => $oldVersion->id,
            'lock_version' => 2,
            'questions_json' => json_encode($updatedQuestions, JSON_THROW_ON_ERROR),
        ])->assertStatus(409);
    }

    public function test_submission_revisions_are_append_only_and_assistant_exports_hide_private_answers_and_formulae(): void
    {
        $admin = $this->administrator('super_admin');
        $form = $this->publishedForm($admin, [
            ['question_key' => 'public_answer', 'label' => 'Public response', 'question_type' => 'short_text', 'is_required' => true, 'assistant_visible' => true],
            ['question_key' => 'private_answer', 'label' => 'Staff-only response', 'question_type' => 'short_text', 'is_required' => true, 'assistant_visible' => false],
        ], ['can_edit_after_submission' => true]);
        $student = Student::factory()->verified()->create();
        $this->asStudent($student);
        $this->post(route('student.forms.save', $form->slug), [
            'intent' => 'submit',
            'answers' => ['public_answer' => 'Initial response', 'private_answer' => 'private medical detail'],
        ])->assertRedirect();
        $submission = FormSubmission::query()->where('student_id', $student->id)->firstOrFail();

        $this->post(route('student.forms.save', $form->slug), [
            'intent' => 'submit',
            'submission_id' => $submission->id,
            'answers' => ['public_answer' => '=HYPERLINK("https://bad.example")', 'private_answer' => 'private medical detail'],
        ])->assertRedirect();
        $this->assertSame(2, $submission->fresh()->submission_revision);
        $this->assertSame(2, FormSubmissionRevision::query()->where('form_submission_id', $submission->id)->count());
        $firstSnapshot = FormSubmissionRevision::query()->where('form_submission_id', $submission->id)->where('revision_number', 1)->firstOrFail();
        $this->assertSame('Initial response', $firstSnapshot->snapshot_answers['public_answer']);

        $assistant = $this->administrator('assistant');
        $response = $this->actingAs($assistant, 'web')->get(route('admin.forms.export', $form));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringNotContainsString('Staff-only response', $csv);
        $this->assertStringNotContainsString('private medical detail', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('transaction_reference', $csv);
    }

    public function test_conditional_logic_rejects_cycles_and_depth_greater_than_three(): void
    {
        $service = app(FormValidationService::class);
        $cycle = [
            ['question_key' => 'one', 'label' => 'One', 'question_type' => 'short_text', 'conditional_logic' => ['logic_version' => 1, 'mode' => 'all', 'conditions' => [['question_key' => 'two', 'operator' => 'is_answered']]]],
            ['question_key' => 'two', 'label' => 'Two', 'question_type' => 'short_text', 'conditional_logic' => ['logic_version' => 1, 'mode' => 'all', 'conditions' => [['question_key' => 'one', 'operator' => 'is_answered']]]],
        ];
        try {
            $service->validateStructure($cycle);
            $this->fail('A conditional dependency cycle must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $deep = [];
        for ($i = 1; $i <= 4; $i++) {
            $deep[] = [
                'question_key' => "q{$i}", 'label' => "Question {$i}", 'question_type' => 'short_text',
                'conditional_logic' => $i === 1 ? null : ['logic_version' => 1, 'mode' => 'all', 'conditions' => [['question_key' => 'q'.($i - 1), 'operator' => 'is_answered']]],
            ];
        }
        try {
            $service->validateStructure($deep);
            $this->fail('Conditional dependency depth over three must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_csv_export_reads_questions_once_and_answers_in_200_row_chunks(): void
    {
        $admin = $this->administrator('admin');
        $form = $this->publishedForm($admin, [
            ['question_key' => 'answer', 'label' => 'Answer', 'question_type' => 'short_text', 'is_required' => false, 'assistant_visible' => true],
        ]);
        $question = $form->activeVersion->questions()->firstOrFail();
        $students = Student::factory()->verified()->count(400)->create();
        $submissionIds = [];
        $submittedAt = now('UTC');
        foreach ($students as $student) {
            $submissionIds[] = FormSubmission::query()->create([
                'form_version_id' => $form->active_version_id,
                'student_id' => $student->id,
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
                'submission_revision' => 1,
            ])->id;
        }
        $answerRows = array_map(fn (int $submissionId): array => [
            'form_submission_id' => $submissionId,
            'form_question_id' => $question->id,
            'value_text' => 'sample',
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ], $submissionIds);
        foreach (array_chunk($answerRows, 200) as $chunk) {
            DB::table('form_answers')->insert($chunk);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($admin, 'web')->get(route('admin.forms.export', $form));
        $csv = $response->streamedContent();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $questionQueries = array_filter($queries, fn (array $query): bool => str_contains(strtolower($query['query']), 'from `form_questions`'));
        $answerQueries = array_filter($queries, fn (array $query): bool => str_contains(strtolower($query['query']), 'from `form_answers`'));
        $this->assertSame(1, count($questionQueries));
        $this->assertSame(2, count($answerQueries));
        $this->assertSame(400, substr_count($csv, 'sample'));
    }

    public function test_form_triggers_assign_forms_and_block_untriggered_direct_access(): void
    {
        $admin = $this->administrator('super_admin');
        $questions = [['question_key' => 'goal', 'label' => 'Learning goal', 'question_type' => 'short_text', 'is_required' => false, 'assistant_visible' => true]];
        $none = $this->publishedForm($admin, $questions, ['title' => 'Always available', 'trigger' => 'none']);
        $afterBooking = $this->publishedForm($admin, $questions, ['title' => 'After booking', 'trigger' => 'after_booking']);
        $afterReschedule = $this->publishedForm($admin, $questions, ['title' => 'After reschedule', 'trigger' => 'after_reschedule']);
        $nextSession = $this->publishedForm($admin, $questions, ['title' => 'Before next session', 'trigger' => 'next_session_check']);
        $student = Student::factory()->verified()->create();
        $this->asStudent($student);

        $this->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Always available')
            ->assertDontSee('After booking')
            ->assertDontSee('After reschedule')
            ->assertDontSee('Before next session');
        $this->get(route('student.forms.show', $afterBooking->slug))->assertNotFound();
        $this->post(route('student.forms.save', $afterBooking->slug), ['intent' => 'draft', 'answers' => []])->assertNotFound();

        $booking = $this->createStudentBooking($student);
        $this->get(route('student.dashboard'))
            ->assertSee('After booking')
            ->assertSee('Before next session')
            ->assertDontSee('After reschedule');
        $this->get(route('student.forms.show', $afterBooking->slug))->assertOk();
        $this->get(route('student.forms.show', $nextSession->slug))->assertOk();
        $this->get(route('student.forms.show', $afterReschedule->slug))->assertNotFound();

        DB::table('session_reschedules')->insert([
            'booking_id' => $booking->id,
            'actor_type' => 'student',
            'actor_id' => $student->id,
            'old_start_at_utc' => $booking->start_at_utc->toDateTimeString(),
            'new_start_at_utc' => $booking->start_at_utc->copy()->addDay()->toDateTimeString(),
            'old_timezone' => 'Africa/Cairo',
            'new_timezone' => 'Africa/Cairo',
            'idempotency_key' => Str::uuid()->toString(),
            'created_at' => now('UTC'),
        ]);

        $this->get(route('student.dashboard'))->assertSee('After reschedule');
        $this->get(route('student.forms.show', $afterReschedule->slug))->assertOk();
    }

    /** @param array<int, array<string, mixed>> $questions @param array<string, mixed> $overrides */
    private function publishedForm(Administrator $admin, array $questions, array $overrides = []): Form
    {
        $form = app(FormBuilderService::class)->create(array_merge([
            'title' => 'Student intake', 'slug' => 'student-intake-'.uniqid(), 'trigger' => 'none',
            'is_mandatory' => false, 'can_edit_after_submission' => false,
        ], $overrides), $questions, $admin);

        return app(FormBuilderService::class)->publish($form->id, $form->active_version_id, $form->lock_version);
    }

    private function asStudent(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession([
            'student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
        ]);
    }

    private function createStudentBooking(Student $student): Booking
    {
        $sessionType = SessionType::query()->create([
            'title' => 'Form assignment lesson',
            'slug' => 'form-assignment-'.Str::lower(Str::random(8)),
            'duration_minutes' => 50,
            'price' => '30.00',
            'currency' => 'USD',
            'active' => true,
        ]);
        $contact = Contact::query()->create([
            'name' => 'Form assignment student',
            'email' => 'form-assignment-'.Str::lower(Str::random(10)).'@example.test',
        ]);
        $start = CarbonImmutable::now('UTC')->addDays(5)->startOfHour();
        $snapshot = app(TimezoneService::class)->createBookingSnapshot($start, $start->addMinutes(50), 'Africa/Cairo', 'Africa/Cairo');

        return Booking::query()->create($snapshot + [
            'student_id' => $student->id,
            'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id,
            'status' => 'confirmed',
            'confirmation_token' => Str::random(64),
            'idempotency_key' => Str::uuid()->toString(),
        ]);
    }

    private function administrator(string $role): Administrator
    {
        return Administrator::create([
            'name' => 'Forms '.$role,
            'email' => uniqid('forms-'.$role.'-').'@example.test',
            'password' => Hash::make('Password123!'),
            'role' => $role,
        ]);
    }
}
