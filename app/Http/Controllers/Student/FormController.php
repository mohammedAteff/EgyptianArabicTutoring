<?php

namespace App\Http\Controllers\Student;

use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormAnswer;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormAssignmentService;
use App\Domains\Forms\Services\FormSubmissionService;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormController extends Controller
{
    public function show(Request $request, string $slug, FormAssignmentService $formAssignments): View
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $form = Form::query()->where('slug', $slug)->firstOrFail();
        abort_unless($form->status === 'published' && $form->published_version_id, 404);
        abort_unless($formAssignments->isAssignedTo($form, $student), 404);
        $studentSubmissions = FormSubmission::query()
            ->where('student_id', $student->id)
            ->whereHas('version', fn ($query) => $query->where('form_id', $form->id))
            ->with('version.questions.options', 'answers.question')
            ->latest('id')
            ->get();
        $latestSubmission = $studentSubmissions->first();
        $publishedSubmission = $studentSubmissions->first(fn (FormSubmission $candidate): bool => (int) $candidate->form_version_id === (int) $form->published_version_id);
        $submission = $publishedSubmission ?? ($request->boolean('new_version') ? null : $latestSubmission);

        $version = $submission?->version ?? $form->publishedVersion()->with('questions.options')->firstOrFail();
        $answers = [];
        foreach ($submission?->answers ?? [] as $answer) {
            $value = $answer->value_text;
            if (in_array($answer->question->question_type, ['multiple_choice'], true)) {
                $value = json_decode((string) $value, true) ?? [];
            } elseif ($answer->question->question_type === 'yes_no') {
                $value = $value === '1';
            }
            $answers[$answer->question->question_key] = $value;
        }

        return view('student.forms.show', [
            'form' => $form,
            'version' => $version,
            'submission' => $submission,
            'answers' => $answers,
            'updateAvailable' => $latestSubmission && (int) $latestSubmission->form_version_id !== (int) $form->published_version_id,
            'readOnly' => $submission?->status === 'submitted' && ! $form->can_edit_after_submission,
            'startNewVersion' => $request->boolean('new_version'),
        ]);
    }

    public function save(Request $request, string $slug, FormSubmissionService $submissions): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $form = Form::query()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'answers' => ['nullable', 'array', 'max:300'],
            'submission_id' => ['nullable', 'integer'],
            'intent' => ['required', 'in:draft,submit'],
        ]);
        $submission = $submissions->save(
            $student,
            $form,
            $data['answers'] ?? [],
            $data['intent'] === 'submit',
            isset($data['submission_id']) ? (int) $data['submission_id'] : null,
        );

        return redirect()->route('student.forms.show', $slug)->with('success', $submission->status === 'submitted' ? 'Your responses were submitted.' : 'Your draft was saved.');
    }

    public function autosave(Request $request, string $slug): JsonResponse
    {
        /** @var Student|null $student */
        $student = $request->attributes->get('student');
        if (! ($student instanceof Student)) {
            $sessionStudentId = $request->session()->get('student_id');
            $student = is_numeric($sessionStudentId) ? Student::verified()->find((int) $sessionStudentId) : null;
        }
        if (! $student) {
            abort(401);
        }

        $studentId = (int) $student->id;

        $form = Form::query()->where('slug', $slug)->firstOrFail();
        abort_unless($form->status === 'published' && $form->published_version_id, 404);

        $publishedVersionId = (int) $form->published_version_id;
        $version = $form->publishedVersion()->with('questions')->firstOrFail();

        $data = $request->validate([
            'answers' => ['nullable', 'array', 'max:300'],
        ]);
        $rawAnswers = $data['answers'] ?? [];

        $submission = app(DatabaseCapability::class)->transaction(function () use ($studentId, $publishedVersionId, $version, $rawAnswers) {
            $lockedStudent = Student::whereKey($studentId)->lockForUpdate()->firstOrFail();

            $draft = FormSubmission::where('form_version_id', $publishedVersionId)
                ->where('student_id', $lockedStudent->id)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();

            if (! $draft) {
                $draft = FormSubmission::create([
                    'form_version_id' => $publishedVersionId,
                    'student_id' => $lockedStudent->id,
                    'status' => 'draft',
                    'submission_revision' => 1,
                ]);
            }

            $questionMap = $version->questions->keyBy('question_key');
            $idMap = $version->questions->keyBy('id');

            foreach ($rawAnswers as $keyOrId => $val) {
                $question = $questionMap->get($keyOrId) ?? $idMap->get($keyOrId);
                if (! $question) {
                    continue;
                }
                $storedValue = is_array($val)
                    ? json_encode($val, JSON_THROW_ON_ERROR)
                    : (is_bool($val) ? ($val ? '1' : '0') : (string) $val);

                FormAnswer::updateOrCreate(
                    ['form_submission_id' => $draft->id, 'form_question_id' => $question->id],
                    ['value_text' => $storedValue]
                );
            }

            $draft->touch();

            return $draft;
        });

        return response()->json([
            'success' => true,
            'draft_id' => $submission->id,
            'form_version_id' => $publishedVersionId,
        ]);
    }
}
