<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LmsQuizService
{
    public function __construct(private LmsProgressService $progress, private LmsLearningDefinition $definitions,
        private TeachingRecordService $students, private AuditLogService $audits) {}

    public function begin(Student $student, Course $course, Lesson $lesson, LessonBlock $block, string $key): QuizAttempt
    {
        Validator::make(['request_key' => $key], ['request_key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($student, $course, $lesson, $block, $key): QuizAttempt {
            [$student,$course,$lesson] = $this->progress->lockTarget($student, $course, $lesson);
            $block = $lesson->blocks()->where('kind', 'quiz')->where('status', 'ready')->lockForUpdate()->findOrFail($block->id);
            $definition = $this->definitions->assessment('quiz', $block->payload);
            $hash = $this->definitions->hash($definition);
            $existing = QuizAttempt::query()->where('student_id', $student->id)->where('request_key', $key)->first();
            if ($existing) {
                abort_unless($existing->block_id === $block->id && $existing->definition_hash === $hash, 409);

                return $existing;
            }
            $query = QuizAttempt::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('definition_hash', $hash);
            $unfinished = (clone $query)->where('status', 'started')->first();
            if ($unfinished) {
                return $unfinished;
            }
            $number = (int) $query->max('number') + 1;
            abort_if($number > $definition['attempt_limit'], 422, 'The attempt limit has been reached.');
            $attempt = new QuizAttempt;
            $attempt->forceFill(['student_id' => $student->id, 'block_id' => $block->id, 'definition_hash' => $hash, 'request_key' => $key,
                'number' => $number, 'original_number' => $number, 'definition' => $definition, 'status' => 'started', 'lock_version' => 1])->save();
            $this->progress->evaluate($student, $lesson);

            return $attempt;
        }, 3);
    }

    /** @param array<int,mixed> $answers */
    public function submit(Student $student, Course $course, Lesson $lesson, LessonBlock $block, int $id, array $answers): QuizAttempt
    {
        return DB::transaction(function () use ($student, $course, $lesson, $block, $id, $answers): QuizAttempt {
            [$student,$course,$lesson] = $this->progress->lockTarget($student, $course, $lesson);
            abort_unless($lesson->blocks()->where('kind', 'quiz')->where('status', 'ready')->whereKey($block->id)->exists(), 404);
            $attempt = QuizAttempt::query()->where('student_id', $student->id)->where('block_id', $block->id)->lockForUpdate()->findOrFail($id);
            $answers = $this->answers($attempt->definition, $answers);
            if ($attempt->status !== 'started') {
                abort_unless($attempt->answers === $answers && $attempt->status !== 'erased', 409, 'This attempt has already been submitted.');

                return $attempt;
            }
            $marks = [];
            foreach ($attempt->definition['questions'] as $i => $q) {
                $answer = $answers[$i];
                $correct = match ($q['type']) {
                    'blank' => in_array($this->definitions->normalizeBlank($answer), array_map($this->definitions->normalizeBlank(...), $q['accepted']), true),
                    'written' => false,
                    default => $answer === $q['answer'],
                };
                $marks[] = $q['type'] === 'written' ? null : ($correct ? $q['points'] : 0);
            }
            $attempt->forceFill(['answers' => $answers, 'marks' => $marks, 'submitted_at' => now('UTC'), 'lock_version' => $attempt->lock_version + 1]);
            $this->score($attempt);
            $attempt->save();
            $this->progress->evaluate($student, $lesson);
            $this->audits->logStudent($student->id, 'lms_quiz_submitted', QuizAttempt::class, $attempt->id, null, ['block_id' => $block->id, 'number' => $attempt->number, 'status' => $attempt->status]);
            app(AnalyticsService::class)->recordLearning($student, 'lms_quiz_submitted', 'attempt:'.$attempt->id,
                ['course_id' => (int) $course->id, 'lesson_id' => (int) $lesson->id, 'block_id' => (int) $block->id, 'attempt_id' => (int) $attempt->id], $attempt->submitted_at);

            return $attempt;
        }, 3);
    }

    /** @param array<int,mixed> $answers
     * @param array<string,mixed> $definition
     * @return array<int,mixed> */
    private function answers(array $definition, array $answers): array
    {
        if (count($answers) > 100 || strlen(json_encode($answers, JSON_THROW_ON_ERROR)) > 300000) {
            $this->invalid('Answers are too large.');
        }
        $clean = [];
        foreach ($definition['questions'] as $i => $q) {
            $answer = $answers[$i] ?? null;
            if (in_array($q['type'], ['blank', 'written'], true)) {
                if (! is_string($answer) || trim($answer) === '' || mb_strlen($answer) > ($q['type'] === 'written' ? 10000 : 500)) {
                    $this->invalid('Answer each written or blank question within its length limit.');
                }
            } elseif ($q['type'] === 'choice') {
                if (is_string($answer) && ctype_digit($answer)) {
                    $answer = (int) $answer;
                }
                if (! is_int($answer) || ! isset($q['options'][$answer])) {
                    $this->invalid('Choose a valid option.');
                }
            } elseif ($q['type'] === 'boolean') {
                $answer = match ($answer) {
                    'true' => true,'false' => false,default => $answer
                };
                if (! is_bool($answer)) {
                    $this->invalid('Choose true or false.');
                }
            } elseif ($q['type'] === 'multiple') {
                if (! is_array($answer) || ! array_is_list($answer) || count($answer) > 20) {
                    $this->invalid('Choose valid options.');
                }
                $answer = array_map(fn ($x) => is_string($x) && ctype_digit($x) ? (int) $x : $x, $answer);
                foreach ($answer as $x) {
                    if (! is_int($x) || ! isset($q['options'][$x])) {
                        $this->invalid('Choose valid options.');
                    }
                }
                $answer = array_values(array_unique($answer));
                sort($answer);
            } elseif ($q['type'] === 'matching') {
                if (! is_array($answer) || ! array_is_list($answer) || count($answer) !== count($q['options'])) {
                    $this->invalid('Match every item.');
                }
                foreach ($answer as $x) {
                    if (! is_string($x) || ! in_array($x, $q['answer'], true)) {
                        $this->invalid('Choose a listed matching target.');
                    }
                }
            }
            $clean[] = $answer;
        }

        return $clean;
    }

    /** @param array<int,int> $marks */
    public function review(Administrator $actor, int $id, int $version, array $marks, ?string $feedback): QuizAttempt
    {
        Gate::forUser($actor)->authorize('manage', Course::class);
        Validator::make(['feedback' => $feedback], ['feedback' => ['nullable', 'string', 'max:5000']])->validate();
        $original = QuizAttempt::query()->findOrFail($id);

        return DB::transaction(function () use ($actor, $original, $version, $marks, $feedback): QuizAttempt {
            Gate::forUser($actor)->authorize('manage', Course::class);
            $student = $this->students->lockStudent($original->student_id);
            $block = LessonBlock::query()->findOrFail($original->block_id);
            Course::query()->lockForUpdate()->findOrFail($block->lesson->course_id);
            $lesson = Lesson::query()->lockForUpdate()->findOrFail($block->lesson_id);
            $attempt = QuizAttempt::query()->lockForUpdate()->findOrFail($original->id);
            abort_unless($attempt->lock_version === $version && $attempt->status === 'pending_review', 409);
            $result = $attempt->marks;
            foreach ($attempt->definition['questions'] as $i => $q) {
                if ($q['type'] === 'written') {
                    $mark = $marks[$i] ?? null;
                    if (! is_int($mark) || $mark < 0 || $mark > $q['points']) {
                        $this->invalid('Grade each written question between zero and its maximum points.');
                    }
                    $result[$i] = $mark;
                }
            }
            $attempt->forceFill(['marks' => $result, 'reviewed_by' => $actor->id, 'feedback' => $feedback, 'lock_version' => $attempt->lock_version + 1]);
            $this->score($attempt);
            $attempt->save();
            $this->progress->evaluate($student, $lesson);
            $this->audits->log('lms_quiz_reviewed', QuizAttempt::class, $attempt->id, null, ['status' => $attempt->status, 'version' => $attempt->lock_version], $actor->id);

            return $attempt;
        }, 3);
    }

    private function score(QuizAttempt $attempt): void
    {
        if (in_array(null, $attempt->marks, true)) {
            $attempt->forceFill(['status' => 'pending_review', 'score' => null, 'passed' => null]);

            return;
        }
        $maximum = array_sum(array_column($attempt->definition['questions'], 'points'));
        $score = round(100 * array_sum($attempt->marks) / $maximum, 2);
        $attempt->forceFill(['status' => 'graded', 'score' => $score, 'passed' => $score >= $attempt->definition['passing_score'], 'graded_at' => now('UTC')]);
    }

    /** Explicit learner projection: no answer keys before the stored review policy permits.
     * @return array<string,mixed> */
    public function project(QuizAttempt $attempt): array
    {
        $review = $attempt->status === 'graded' && $attempt->definition['review_policy'] === 'after_grading';
        $questions = [];
        foreach ($attempt->definition['questions'] as $i => $q) {
            $safe = ['type' => $q['type'], 'prompt' => $q['prompt'], 'points' => $q['points'], 'options' => $q['options'] ?? []];
            $answer = $attempt->answers[$i] ?? null;
            $safe['response_label'] = $this->answerLabel($q, $answer);
            if ($q['type'] === 'matching') {
                $targets = $q['answer'];
                sort($targets, SORT_STRING);
                $safe['targets'] = $targets;
            }
            if ($review) {
                $safe['correct'] = $q['type'] === 'blank' ? $q['accepted'] : ($q['answer'] ?? null);
                $safe['review_label'] = $this->answerLabel($q, $safe['correct']);
                $safe['mark'] = $attempt->marks[$i];
            }
            $questions[] = $safe;
        }

        return ['id' => $attempt->id, 'title' => $attempt->definition['title'], 'number' => $attempt->number, 'status' => $attempt->status,
            'score' => $attempt->score, 'passed' => $attempt->passed, 'questions' => $questions, 'answers' => $attempt->answers, 'feedback' => $attempt->feedback];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['answers' => $message]);
    }

    /** @param array<string,mixed> $question */
    private function answerLabel(array $question, mixed $answer): string
    {
        if ($answer === null) {
            return $question['type'] === 'written' ? 'Manually reviewed' : '';
        }
        if ($question['type'] === 'choice') {
            return $question['options'][$answer] ?? '';
        }
        if ($question['type'] === 'multiple') {
            return implode(', ', array_map(fn (int $index): string => $question['options'][$index], $answer));
        }
        if ($question['type'] === 'matching') {
            return implode('; ', array_map(fn (int $i): string => $question['options'][$i].' → '.$answer[$i], array_keys($answer)));
        }

        return is_bool($answer) ? ($answer ? 'True' : 'False') : (is_array($answer) ? implode(', ', $answer) : (string) $answer);
    }
}
