<?php

namespace App\Domains\Forms\Services;

use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormSubmissionQuery
{
    /** @return array<string, mixed> */
    public function filters(Request $request, Form $form): array
    {
        $filters = $request->validate([
            'version_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['draft', 'submitted'])],
            'q' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);
        $filters['version_id'] = (int) ($filters['version_id'] ?? $form->published_version_id ?? $form->active_version_id);
        abort_unless($form->versions()->whereKey($filters['version_id'])->exists(), 404);

        return $filters;
    }

    /** @return Builder<FormSubmission> */
    public function query(array $filters, bool $isAssistant, bool $includeQuestionLabels = true): Builder
    {
        $query = FormSubmission::query()->select(['id', 'form_version_id', 'student_id', 'status', 'submitted_at', 'created_at', 'submission_revision'])
            ->where('form_version_id', $filters['version_id'])
            ->with(['student:id,first_name,last_name,email,phone', 'version:id,form_id,version_number'])
            ->with(['answers' => function ($query) use ($isAssistant, $includeQuestionLabels): void {
                $query->select(['id', 'form_submission_id', 'form_question_id', 'value_text'])
                    ->when($isAssistant, fn ($answers) => $answers->whereHas('question', fn ($questions) => $questions->where('assistant_visible', true)))
                    ->orderBy('form_question_id');
                if ($includeQuestionLabels) {
                    $query->with(['question:id,question_key,label,question_type,assistant_visible,sort_order']);
                }
            }]);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($filters['q'])).'%';
            $query->whereHas('student', fn (Builder $students) => $students->where(function (Builder $students) use ($term): void {
                $students->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term);
            }));
        }
        foreach (['date_from' => '>=', 'date_to' => '<'] as $filter => $operator) {
            if (! empty($filters[$filter])) {
                $day = CarbonImmutable::parse($filters[$filter], app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
                $query->where('created_at', $operator, ($filter === 'date_to' ? $day->addDay() : $day)->utc());
            }
        }

        return $query->orderBy('id');
    }
}
