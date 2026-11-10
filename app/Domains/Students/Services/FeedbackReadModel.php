<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackReadState;
use App\Domains\Students\Models\Student;
use App\Policies\FeedbackPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class FeedbackReadModel
{
    public function __construct(private FeedbackService $actions, private FeedbackPolicy $policy) {}

    /** @return Builder<FeedbackConversation> */
    public function conversations(Administrator|Student $actor): Builder
    {
        abort_unless($this->policy->access($actor), 403);
        $type = $this->actions->actorType($actor);
        $query = FeedbackConversation::query()->available()
            ->when($actor instanceof Student, fn (Builder $q) => $q->where('student_id', $actor->id))
            ->withExists(['events as has_unread' => fn (Builder $q) => $this->incoming($q, $actor)
                ->whereRaw('feedback_events.sequence > COALESCE((SELECT last_sequence FROM feedback_read_states WHERE conversation_id = feedback_conversations.id AND actor_type = ? AND actor_id = ?), 0)', [$type, $actor->id])]);

        return $query;
    }

    public function incoming(Builder|HasMany $query, Administrator|Student $actor): Builder|HasMany
    {
        if ($actor instanceof Student) {
            return $query->where('actor_type', 'administrator');
        }

        return $query->where(fn (Builder $q) => $q->where('actor_type', '!=', $this->actions->actorType($actor))
            ->orWhere('actor_id', '!=', $actor->id)->orWhereNull('actor_id'));
    }

    public function conversation(Administrator|Student $actor, int $id): FeedbackConversation
    {
        $conversation = $this->conversations($actor)->with('student')->findOrFail($id);
        abort_unless($this->policy->view($actor, $conversation), 404);

        return $conversation;
    }

    public function studentReadSequence(FeedbackConversation $conversation): int
    {
        return (int) FeedbackReadState::query()->where('conversation_id', $conversation->id)
            ->where('actor_type', 'student')->where('actor_id', $conversation->student_id)->value('last_sequence');
    }

    /** @return array<string,mixed> */
    public function segment(Administrator|Student $actor, FeedbackConversation $conversation, ?int $page = null): array
    {
        $type = $this->actions->actorType($actor);
        $watermark = (int) FeedbackReadState::query()->where('conversation_id', $conversation->id)->where('actor_type', $type)->where('actor_id', $actor->id)->value('last_sequence');
        $unread = $this->incoming($conversation->events(), $actor)->where('sequence', '>', $watermark)
            ->where('sequence', '<=', $conversation->last_sequence)->min('sequence');
        $latest = max(1, (int) ceil($conversation->last_sequence / 50));
        $firstUnread = $unread ? (int) ceil($unread / 50) : null;
        $page ??= $firstUnread ?? $latest;
        abort_if($page < 1 || $page > $latest, 404);
        $from = ($page - 1) * 50 + 1;
        $to = min($conversation->last_sequence, $page * 50);
        $events = $conversation->events()->whereBetween('sequence', [$from, $to])->orderBy('sequence')->limit(50)->get();
        $receipt = Crypt::encryptString(json_encode([
            'conversation' => $conversation->id, 'actor_type' => $type, 'actor_id' => $actor->id,
            'from' => $from, 'to' => $to, 'expires' => now('UTC')->addMinutes(30)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return compact('events', 'receipt', 'page', 'latest', 'firstUnread', 'watermark');
    }

    public function acknowledge(Administrator|Student $actor, int $conversationId, string $receipt): int
    {
        try {
            $proof = json_decode(Crypt::decryptString($receipt), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(422, __('feedback.invalid_receipt'));
        }
        abort_unless(is_array($proof) && ($proof['conversation'] ?? null) === $conversationId
            && ($proof['actor_type'] ?? null) === $this->actions->actorType($actor) && ($proof['actor_id'] ?? null) === $actor->id
            && is_int($proof['from'] ?? null) && is_int($proof['to'] ?? null) && is_int($proof['expires'] ?? null)
            && $proof['expires'] >= now('UTC')->timestamp, 422, __('feedback.invalid_receipt'));
        $owner = FeedbackConversation::query()->whereKey($conversationId)->value('student_id');
        abort_unless($owner, 404);

        return DB::transaction(function () use ($actor, $conversationId, $owner, $proof): int {
            $student = $this->actions->lockStudent($actor, (int) $owner);
            $conversation = FeedbackConversation::query()->available()->where('student_id', $student->id)->lockForUpdate()->findOrFail($conversationId);
            abort_unless($this->policy->view($actor, $conversation), 404);
            $reader = FeedbackReadState::query()->firstOrCreate([
                'conversation_id' => $conversationId, 'actor_type' => $this->actions->actorType($actor), 'actor_id' => $actor->id,
            ], ['last_sequence' => 0]);
            $from = $proof['from'];
            $to = $proof['to'];
            abort_unless($from >= 1 && $to >= $from && $to - $from < 50 && $to <= $conversation->last_sequence, 422, __('feedback.invalid_receipt'));
            if ($to <= $reader->last_sequence) {
                return $reader->last_sequence;
            }
            // Only an own-authored gap may be skipped; unseen incoming events always block advancement.
            $gap = $this->incoming($conversation->events(), $actor)->where('sequence', '>', $reader->last_sequence)->where('sequence', '<', $from)->exists();
            abort_if($gap, 409, __('feedback.read_gap'));
            $reader->forceFill(['last_sequence' => $to])->save();

            return $to;
        }, 5);
    }

    /** @return array<string,mixed> */
    public function filters(Request $request, bool $admin): array
    {
        $rules = ['status' => ['nullable', Rule::in(FeedbackConversation::STATUSES)], 'page' => ['nullable', 'integer', 'between:1,100000']];
        if ($admin) {
            $rules += [
                'type' => ['nullable', Rule::in(FeedbackConversation::TYPES)], 'area' => ['nullable', Rule::in(FeedbackConversation::AREAS)],
                'student_id' => ['nullable', 'integer', 'min:1'], 'student_search' => ['nullable', 'string', 'max:100'],
                'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
                'read' => ['nullable', Rule::in(['unread', 'read'])],
            ];
        }

        return $request->validate($rules, [], [
            'status' => __('feedback.status'), 'page' => __('feedback.history'),
            'type' => __('feedback.type'), 'area' => __('feedback.area'),
            'student_id' => __('feedback.student_filter'), 'student_search' => __('feedback.student_search'),
            'date_from' => __('feedback.date_from'), 'date_to' => __('feedback.date_to'), 'read' => __('feedback.read_filter'),
        ]);
    }

    public function apply(Builder $query, Administrator|Student $actor, array $filters, string $timezone): Builder
    {
        foreach (['status', 'type', 'area', 'student_id'] as $key) {
            $query->when(! empty($filters[$key]), fn (Builder $q) => $q->where($key, $filters[$key]));
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $filters['date_from'], $timezone)->utc());
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $filters['date_to'], $timezone)->addDay()->utc());
        }
        if (! empty($filters['read'])) {
            $query->whereHas('events', function (Builder $q) use ($actor): void {
                $this->incoming($q, $actor)->whereRaw('feedback_events.sequence > COALESCE((SELECT last_sequence FROM feedback_read_states WHERE conversation_id = feedback_conversations.id AND actor_type = ? AND actor_id = ?), 0)', [$this->actions->actorType($actor), $actor->id]);
            }, $filters['read'] === 'unread' ? '>=' : '<', 1);
        }

        return $query->orderByDesc('last_activity_at')->orderByDesc('id');
    }
}
