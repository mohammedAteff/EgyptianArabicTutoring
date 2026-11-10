<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackEvent;
use App\Domains\Students\Models\FeedbackOperation;
use App\Domains\Students\Models\Student;
use App\Policies\FeedbackPolicy;
use App\Policies\StudentTeachingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FeedbackService
{
    public function __construct(private FeedbackPolicy $policy, private FeedbackContext $contexts, private FeedbackNotifications $notifications) {}

    public function actorType(Administrator|Student $actor): string
    {
        return $actor instanceof Student ? 'student' : 'administrator';
    }

    /** Administrator locks precede Student locks, matching account restriction operations.
     * Student → conversation is also the lifecycle and notification lock order.
     */
    public function lockStudent(Administrator|Student $actor, int $studentId): Student
    {
        if ($actor instanceof Administrator) {
            $current = Administrator::query()->lockForUpdate()->find($actor->id);
            abort_unless($current && $this->policy->access($current), 403);
        }
        $student = Student::query()->lockForUpdate()->find($studentId);
        abort_unless($student && app(StudentTeachingPolicy::class)->usePortal($student), 404);
        abort_unless($this->policy->access($actor), $actor instanceof Student ? 404 : 403);
        abort_if($actor instanceof Student && $actor->id !== $student->id, 404);

        return $student;
    }

    /** @param array<string,mixed> $input */
    public function create(Student $actor, array $input): FeedbackOperation
    {
        return DB::transaction(function () use ($actor, $input): FeedbackOperation {
            $student = $this->lockStudent($actor, $actor->id);
            $data = $this->validate($input, 'create');
            if ($receipt = $this->replay($actor, $student->id, 0, $data)) {
                return $receipt;
            }
            $this->quota($student, 'create');
            if ($data['context_type'] !== null) {
                abort_unless((bool) $this->contexts->resolve($student, $data['context_type'], $data['context_id']), 404);
            }
            $conversation = FeedbackConversation::query()->create([
                'student_id' => $student->id, 'subject' => $data['subject'], 'type' => $data['type'], 'area' => $data['area'],
                'context_type' => $data['context_type'], 'context_id' => $data['context_id'], 'status' => 'open',
                'version' => 1, 'last_sequence' => 0, 'last_activity_at' => now('UTC'),
            ]);
            $this->event($conversation, $actor, ['kind' => 'message', 'body' => $data['body']]);
            $conversation->save();
            $receipt = $this->receipt($actor, $conversation, 0, $data);
            $this->notifications->send($actor, $conversation, $receipt);

            return $receipt;
        }, 5);
    }

    /** @param array<string,mixed> $input */
    public function mutate(Administrator|Student $actor, int $conversationId, array $input): FeedbackOperation
    {
        $studentId = FeedbackConversation::query()->whereKey($conversationId)->value('student_id');
        abort_unless($studentId, 404);

        return DB::transaction(function () use ($actor, $conversationId, $studentId, $input): FeedbackOperation {
            $student = $this->lockStudent($actor, (int) $studentId);
            $conversation = FeedbackConversation::query()->available()->where('student_id', $student->id)->lockForUpdate()->findOrFail($conversationId);
            abort_unless($this->policy->view($actor, $conversation), 404);
            $action = Validator::make($input, ['action' => ['required', Rule::in(['reply', 'clarify', 'resolve', 'close', 'reopen'])]],
                ['action.required' => __('feedback.required'), 'action.in' => __('feedback.invalid_choice')])->validate()['action'];
            abort_if($actor instanceof Student && $action !== 'reply', 403);
            $data = $this->validate($input, $action);
            if ($receipt = $this->replay($actor, $student->id, $conversation->id, $data)) {
                return $receipt;
            }
            if ($actor instanceof Student) {
                $this->quota($student, 'reply');
            }
            if ($action !== 'reply') {
                abort_unless($data['version'] === $conversation->version, 409, __('feedback.stale'));
            }
            $before = $conversation->last_sequence;
            if (in_array($action, ['reply', 'clarify'], true)) {
                abort_if($conversation->status === 'closed', 409, __('feedback.closed_reply'));
                $this->event($conversation, $actor, ['kind' => 'message', 'body' => $data['body']]);
            }
            $next = match ($action) {
                'reply' => $actor instanceof Student ? 'open' : $conversation->status,
                'clarify' => 'waiting_student',
                'resolve' => in_array($conversation->status, ['open', 'waiting_student'], true) ? 'resolved' : null,
                'close' => $conversation->status === 'resolved' ? 'closed' : null,
                'reopen' => $conversation->status === 'closed' ? 'open' : null,
                default => throw new \UnhandledMatchError('Unsupported feedback action.'),
            };
            abort_if($next === null, 409, __('feedback.invalid_transition'));
            if ($next !== $conversation->status) {
                $this->event($conversation, $actor, ['kind' => 'status', 'from_status' => $conversation->status, 'to_status' => $next]);
                $conversation->status = $next;
            }
            if ($conversation->last_sequence !== $before) {
                $conversation->version++;
                $conversation->last_activity_at = CarbonImmutable::now('UTC');
                $conversation->save();
            }
            $receipt = $this->receipt($actor, $conversation, $conversation->id, $data);
            if ($conversation->last_sequence !== $before) {
                $this->notifications->send($actor, $conversation, $receipt);
            }

            return $receipt;
        }, 5);
    }

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function validate(array $input, string $action): array
    {
        $input['subject'] = isset($input['subject']) && is_string($input['subject']) ? preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $input['subject']) : ($input['subject'] ?? null);
        $rules = ['request_key' => ['required', 'uuid']];
        if ($action === 'create') {
            $rules += [
                'subject' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:5000'],
                'type' => ['required', Rule::in(FeedbackConversation::TYPES)], 'area' => ['required', Rule::in(FeedbackConversation::AREAS)],
                'context_type' => ['nullable', 'required_with:context_id', Rule::in(['course', 'lesson', 'homework', 'booking'])],
                'context_id' => ['nullable', 'required_with:context_type', 'integer', 'min:1'],
            ];
        } else {
            $rules['action'] = ['required', Rule::in(['reply', 'clarify', 'resolve', 'close', 'reopen'])];
            $input['action'] = $action;
            if (in_array($action, ['reply', 'clarify'], true)) {
                $rules['body'] = ['required', 'string', 'max:5000'];
            }
            if ($action !== 'reply') {
                $rules['version'] = ['required', 'integer', 'min:1'];
            }
        }
        $validator = Validator::make($input, $rules, [
            'required' => __('feedback.required'), 'required_with' => __('feedback.required'),
            'string' => __('feedback.invalid'), 'uuid' => __('feedback.invalid_identity'), 'in' => __('feedback.invalid_choice'),
            'integer' => __('feedback.invalid'), 'min' => __('feedback.invalid'),
            'subject.max' => __('feedback.too_long', ['count' => 160]), 'body.max' => __('feedback.too_long', ['count' => 5000]),
        ]);
        $validator->after(function ($validator) use ($input, $rules): void {
            foreach (['subject', 'body'] as $field) {
                if (isset($rules[$field]) && is_string($input[$field] ?? null) && ! preg_match('/[^\s\p{Z}]/u', $input[$field])) {
                    $validator->errors()->add($field, __('feedback.required'));
                }
            }
        });
        $data = $validator->validate();
        $data['action'] = $action;
        if ($action === 'create') {
            $data['context_type'] = $data['context_type'] ?? null;
            $data['context_id'] = $data['context_type'] !== null ? (int) $data['context_id'] : null;
        }
        if (isset($data['version'])) {
            $data['version'] = (int) $data['version'];
        }

        return $data;
    }

    /** @param array<string,mixed> $data */
    private function fingerprint(array $data): string
    {
        unset($data['request_key']);
        ksort($data);

        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), (string) config('app.key'));
    }

    /** Original student actor IDs remain provenance after a merge; current ownership authorizes thread replays.
     * @param  array<string,mixed>  $data
     */
    private function replay(Administrator|Student $actor, int $studentId, int $scope, array $data): ?FeedbackOperation
    {
        $matches = FeedbackOperation::query()->where('student_id', $studentId)->where('actor_type', $this->actorType($actor))
            ->when($actor instanceof Administrator, fn ($query) => $query->where('actor_id', $actor->id))
            ->where('scope_id', $scope)->where('request_key', $data['request_key'])->limit(2)->get();
        abort_if($matches->count() > 1, 409, __('feedback.identity_conflict'));
        $receipt = $matches->first();
        if ($receipt) {
            abort_unless(hash_equals($receipt->fingerprint, $this->fingerprint($data)), 409, __('feedback.identity_conflict'));
        }

        return $receipt;
    }

    /** @param array<string,mixed> $data */
    private function receipt(Administrator|Student $actor, FeedbackConversation $conversation, int $scope, array $data): FeedbackOperation
    {
        return FeedbackOperation::query()->create([
            'student_id' => $conversation->student_id, 'conversation_id' => $conversation->id,
            'actor_type' => $this->actorType($actor), 'actor_id' => $actor->id, 'scope_id' => $scope,
            'request_key' => $data['request_key'], 'action' => $data['action'], 'fingerprint' => $this->fingerprint($data),
            'result_sequence' => $conversation->last_sequence, 'result_version' => $conversation->version, 'created_at' => now('UTC'),
        ]);
    }

    /** @param array<string,mixed> $attributes */
    private function event(FeedbackConversation $conversation, Administrator|Student $actor, array $attributes): void
    {
        FeedbackEvent::query()->create($attributes + [
            'conversation_id' => $conversation->id, 'sequence' => ++$conversation->last_sequence,
            'actor_type' => $this->actorType($actor), 'actor_id' => $actor->id, 'created_at' => now('UTC'),
        ]);
    }

    private function quota(Student $student, string $action): void
    {
        $seconds = $action === 'create' ? 3600 : 60;
        $limit = (int) config($action === 'create' ? 'feedback.new_conversations_per_hour' : 'feedback.replies_per_minute');
        $count = FeedbackOperation::query()->where('student_id', $student->id)->where('actor_type', 'student')
            ->where('action', $action)->where('created_at', '>', now('UTC')->subSeconds($seconds))->count();
        abort_if($count >= $limit, 429, __('feedback.rate_limit'), ['Retry-After' => (string) $seconds]);
    }
}
