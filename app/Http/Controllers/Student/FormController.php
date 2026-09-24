<?php

namespace App\Http\Controllers\Student;

use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormAssignmentService;
use App\Domains\Forms\Services\FormSubmissionService;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
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
}
