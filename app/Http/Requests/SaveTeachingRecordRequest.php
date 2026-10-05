<?php

namespace App\Http\Requests;

use App\Domains\Students\Models\Student;
use App\Policies\StudentTeachingPolicy;
use App\Rules\SafeLessonUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeachingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student && $this->user() !== null
            && app(StudentTeachingPolicy::class)->manageTeaching($this->user(), $student);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $shared = ['student_visible' => ['required', 'boolean']];
        $lesson = ['booking_id' => ['nullable', 'integer'], 'record_id' => ['nullable', 'integer', 'min:1']];
        $title = ['title' => ['required', 'string', 'max:200']];
        $text = ['nullable', 'string', 'max:5000'];

        return $lesson + match ($this->route('kind')) {
            'homework' => $shared + $title + [
                'instructions' => ['required', 'string', 'max:5000'],
                'assigned_date' => ['required', 'date_format:Y-m-d'],
                'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:assigned_date'],
                'status' => ['required', Rule::in(['assigned', 'in_progress', 'submitted', 'completed'])],
                'feedback' => $text, 'url' => ['nullable', 'string', 'max:2048', new SafeLessonUrl],
                'resource_id' => ['nullable', 'integer'], 'lesson_material_id' => ['nullable', 'integer'],
            ],
            'plan' => $shared + $title + [
                'goals' => ['required', 'string', 'max:5000'], 'focus_areas' => $text,
                'current_level' => ['nullable', 'string', 'max:100'], 'notes' => $text,
                'start_date' => ['required', 'date_format:Y-m-d'],
                'status' => ['required', Rule::in(['active', 'paused', 'completed'])],
            ],
            'milestone' => $title + [
                'learning_plan_id' => ['required', 'integer'],
                'status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
            ],
            'preparation' => ['body' => ['required', 'string', 'max:5000']],
            'resource' => $shared + ['resource_id' => ['required', 'integer'], 'instructions' => $text, 'withdraw' => ['nullable', 'boolean']],
            'error' => $shared + [
                'category' => ['required', Rule::in(['pronunciation', 'vocabulary', 'grammar'])],
                'mistake' => ['required', 'string', 'max:500'], 'correction' => ['required', 'string', 'max:5000'],
                'notes' => $text, 'status' => ['required', Rule::in(['practising', 'improved', 'resolved'])],
            ],
            'tag' => $shared + ['label' => ['required', 'string', 'max:40', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} -]*$/u']],
            default => abort(404),
        };
    }
}
