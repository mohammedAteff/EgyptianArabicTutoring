<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormQuestion;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormBuilderService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormController extends Controller
{
    public function index(): View
    {
        $role = Auth::guard('web')->user()?->role;

        return view('admin.forms.index', [
            'forms' => Form::query()->when($role === 'assistant', fn (Builder $query) => $query->where('status', 'published'))->with(['activeVersion', 'publishedVersion'])->orderByDesc('updated_at')->paginate(25),
            'canManageForms' => in_array($role, ['super_admin', 'admin'], true),
        ]);
    }

    public function create()
    {
        return view('admin.forms.edit', ['form' => null, 'questionsJson' => '[]']);
    }

    public function store(Request $request, FormBuilderService $builder, AuditLogService $audit)
    {
        $data = $this->validatedMetadata($request);
        $questions = $this->decodeQuestions($data['questions_json']);
        unset($data['questions_json'], $data['base_version_id'], $data['lock_version'], $data['changelog']);
        $form = $builder->create($data, $questions, $this->administrator());
        $audit->log('form.created', Form::class, $form->id, null, ['form_id' => $form->id]);

        return redirect()->route('admin.forms.edit', $form)->with('success', 'Form draft created.');
    }

    public function edit(Form $form)
    {
        $form->load('activeVersion.questions.options');
        $questions = $form->activeVersion?->questions->map(fn (FormQuestion $question): array => [
            'question_key' => $question->question_key,
            'label' => $question->label,
            'description' => $question->description,
            'question_type' => $question->question_type,
            'is_required' => $question->is_required,
            'assistant_visible' => $question->assistant_visible,
            'sort_order' => $question->sort_order,
            'validation_rules' => $question->validation_rules,
            'presentation_config' => $question->presentation_config,
            'conditional_logic' => $question->conditional_logic,
            'options' => $question->options->map(fn ($option): array => ['label' => $option->label, 'value' => $option->value, 'sort_order' => $option->sort_order])->all(),
        ])->all() ?? [];

        return view('admin.forms.edit', ['form' => $form, 'questionsJson' => json_encode($questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]);
    }

    public function update(Request $request, Form $form, FormBuilderService $builder, AuditLogService $audit)
    {
        $data = $this->validatedMetadata($request, $form);
        $questions = $this->decodeQuestions($data['questions_json']);
        $baseVersionId = (int) $data['base_version_id'];
        $lockVersion = (int) $data['lock_version'];
        unset($data['questions_json'], $data['base_version_id'], $data['lock_version']);
        $updated = $builder->update($form->id, $data, $questions, $baseVersionId, $lockVersion);
        $audit->log('form.updated', Form::class, $updated->id, null, ['form_id' => $updated->id, 'version_id' => $updated->active_version_id]);

        return redirect()->route('admin.forms.edit', $updated)->with('success', 'Form saved.');
    }

    public function publish(Request $request, Form $form, FormBuilderService $builder, AuditLogService $audit)
    {
        $data = $request->validate(['base_version_id' => ['required', 'integer'], 'lock_version' => ['required', 'integer']]);
        $updated = $builder->publish($form->id, (int) $data['base_version_id'], (int) $data['lock_version']);
        $audit->log('form.published', Form::class, $updated->id, null, ['form_id' => $updated->id, 'version_id' => $updated->active_version_id]);

        return back()->with('success', 'Form published.');
    }

    public function archive(Request $request, Form $form, FormBuilderService $builder, AuditLogService $audit)
    {
        $data = $request->validate(['lock_version' => ['required', 'integer']]);
        $updated = $builder->archive($form->id, (int) $data['lock_version']);
        $audit->log('form.archived', Form::class, $updated->id, null, ['form_id' => $updated->id]);

        return back()->with('success', 'Form archived.');
    }

    public function submissions(Request $request, Form $form): View
    {
        $isAssistant = Auth::guard('web')->user()?->role === 'assistant';
        $versions = $form->versions()->orderByDesc('version_number')->get(['id', 'form_id', 'version_number']);
        $versionId = (int) $request->query('version_id', $form->published_version_id ?? $form->active_version_id);
        abort_unless($versions->contains('id', $versionId), 404);
        $submissions = FormSubmission::query()
            ->select(['id', 'form_version_id', 'student_id', 'status', 'submitted_at', 'submission_revision'])
            ->where('form_version_id', $versionId)
            ->with(['student:id,first_name,last_name,email,phone', 'version:id,form_id,version_number'])
            ->with(['answers' => function ($query) use ($isAssistant): void {
                $query->select(['form_answers.id', 'form_answers.form_submission_id', 'form_answers.form_question_id', 'form_answers.value_text'])
                    ->when($isAssistant, fn ($answers) => $answers->whereHas('question', fn ($questions) => $questions->where('assistant_visible', true)))
                    ->with(['question:id,question_key,label,question_type,assistant_visible']);
            }])
            ->orderByDesc('id')
            ->paginate(50);

        return view('admin.forms.submissions', compact('form', 'versions', 'versionId', 'submissions', 'isAssistant'));
    }

    public function export(Request $request, Form $form): StreamedResponse
    {
        $isAssistant = Auth::guard('web')->user()?->role === 'assistant';
        $versions = $form->versions()->pluck('id')->all();
        $versionId = (int) $request->query('version_id', $form->published_version_id ?? $form->active_version_id);
        abort_unless(in_array($versionId, array_map('intval', $versions), true), 404);
        $questions = FormQuestion::query()
            ->select(['id', 'form_version_id', 'question_key', 'label', 'question_type', 'assistant_visible', 'sort_order'])
            ->where('form_version_id', $versionId)
            ->where('question_type', '!=', 'info_block')
            ->when($isAssistant, fn (Builder $query) => $query->where('assistant_visible', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $columns = $questions->pluck('label')->all();

        return Response::streamDownload(function () use ($versionId, $questions, $columns, $isAssistant): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_merge(['Submission ID', 'Student ID', 'Submitted At'], $columns));
            FormSubmission::query()
                ->select(['id', 'form_version_id', 'student_id', 'status', 'submitted_at'])
                ->where('form_version_id', $versionId)
                ->with(['answers' => function ($query) use ($questions, $isAssistant): void {
                    $query->select(['id', 'form_submission_id', 'form_question_id', 'value_text'])
                        ->whereIn('form_question_id', $questions->pluck('id'))
                        ->when($isAssistant, fn ($answers) => $answers->whereHas('question', fn ($question) => $question->where('assistant_visible', true)));
                }])
                ->orderBy('id')
                ->chunkById(200, function ($submissions) use ($handle, $questions): void {
                    foreach ($submissions as $submission) {
                        $answerMap = $submission->answers->keyBy('form_question_id');
                        $row = [$submission->id, $submission->student_id, $submission->submitted_at?->toIso8601String()];
                        foreach ($questions as $question) {
                            $value = (string) ($answerMap->get($question->id)?->value_text ?? '');
                            $row[] = preg_match('/^[=+\-@\t\r]/u', $value) ? "'{$value}" : $value;
                        }
                        fputcsv($handle, $row);
                    }
                });
            fclose($handle);
        }, "form-{$form->id}-submissions.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedMetadata(Request $request, ?Form $form = null): array
    {
        $metadata = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('forms', 'slug')->ignore($form?->id)],
            'description' => ['nullable', 'string', 'max:10000'],
            'prompt_trigger' => ['required', Rule::in(['none', 'after_booking', 'after_reschedule', 'next_session_check'])],
            'is_mandatory' => ['nullable', 'boolean'],
            'can_edit_after_submission' => ['nullable', 'boolean'],
            'questions_json' => ['required', 'string', 'max:500000'],
            'base_version_id' => [$form ? 'required' : 'nullable', 'integer'],
            'lock_version' => [$form ? 'required' : 'nullable', 'integer'],
            'changelog' => ['nullable', 'string', 'max:2000'],
        ]);

        $metadata['is_mandatory'] = $request->boolean('is_mandatory');
        $metadata['can_edit_after_submission'] = $request->boolean('can_edit_after_submission');

        return $metadata;
    }

    /** @return array<int, array<string, mixed>> */
    private function decodeQuestions(string $json): array
    {
        try {
            $questions = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['questions_json' => 'Questions must be valid JSON.']);
        }
        if (! is_array($questions) || ! array_is_list($questions)) {
            throw ValidationException::withMessages(['questions_json' => 'Questions must be a JSON list.']);
        }

        return $questions;
    }

    private function administrator(): Administrator
    {
        $administrator = Auth::guard('web')->user();
        abort_unless($administrator instanceof Administrator, 403);

        return $administrator;
    }
}
