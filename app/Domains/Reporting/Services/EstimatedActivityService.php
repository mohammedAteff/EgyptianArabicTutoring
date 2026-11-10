<?php

namespace App\Domains\Reporting\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EstimatedActivityService
{
    public function __construct(private EstimatedActivityGate $gate, private EstimatedActivityRegistry $registry) {}

    /** @return array<string,mixed> */
    public function anchor(Request $request, string $domain): array
    {
        abort_unless((bool) $this->gate->allowed($request, $domain), 403);
        $data = $this->payload($request, ['kind', 'record', 'stream', 'interruption'], [
            'kind' => ['required', Rule::in(array_keys(EstimatedActivityRegistry::CONTEXTS))],
            'record' => ['present', 'integer', 'min:0'], 'stream' => ['required', 'uuid'],
            'interruption' => ['sometimes', Rule::in(['hidden', 'unfocused', 'suspended', 'unsupported', 'grace_expired', 'network_failure', 'context_changed', 'window_interrupted'])],
        ]);
        abort_unless(is_int($data['record']), 422);

        return $this->transaction(function () use ($request, $domain, $data): array {
            $access = $this->gate->allowed($request, $domain, true);
            abort_unless((bool) $access, 403);
            $this->limit($access['grant']->id, 'anchor', EstimatedActivityDefinition::ANCHORS_PER_MINUTE);
            $contextHash = $this->registry->signature($data['kind'], $data['record']);
            $category = $this->registry->validate($access['principal'], $data['kind'], $data['record']);
            $now = CarbonImmutable::now('UTC');
            $streams = DB::table('estimated_activity_streams')->where('grant_id', $access['grant']->id);
            $old = (clone $streams)->where('client_stream', $data['stream'])->where('session_hash', $access['binding'])->pluck('id');
            DB::table('estimated_activity_coverage')->whereIn('stream_id', $old)->whereNull('ended_at')
                ->update(['ended_at' => EstimatedActivityDefinition::stamp($now), 'end_reason' => $data['interruption'] ?? 'reanchored']);
            (clone $streams)->whereIn('id', $old)->delete();
            abort_if((clone $streams)->where('expires_at', '>', EstimatedActivityDefinition::stamp($now))
                ->where('received_at', '>', EstimatedActivityDefinition::stamp($now->subSeconds(20)))->count() >= EstimatedActivityDefinition::MAX_STREAMS, 429);
            $token = Str::random(64);
            $id = (string) Str::uuid();
            $expires = $now->addMinutes(15)->min(CarbonImmutable::parse($access['grant']->expires_at, 'UTC'))->min(CarbonImmutable::parse($access['run']->expires_at, 'UTC'));
            DB::table('estimated_activity_streams')->insert(['id' => $id, 'grant_id' => $access['grant']->id, 'token_hash' => EstimatedActivityDefinition::fingerprint($token),
                'session_hash' => $access['binding'], 'client_stream' => $data['stream'], 'generation' => $access['run']->generation,
                'definition' => EstimatedActivityDefinition::VERSION, 'kind' => $data['kind'], 'record_id' => $data['record'], 'category' => $category,
                'context_hash' => $contextHash, 'sequence' => 0, 'received_at' => EstimatedActivityDefinition::stamp($now), 'expires_at' => EstimatedActivityDefinition::stamp($expires)]);
            DB::table('estimated_activity_coverage')->insert($this->evidence($access, $id, $data['kind'], $data['record'], $category)
                + ['anchored_at' => EstimatedActivityDefinition::stamp($now)]);

            return ['permit' => $token, 'sequence' => 0, 'seconds' => 0, 'definition' => EstimatedActivityDefinition::VERSION];
        });
    }

    /** @return array<string,mixed> */
    public function checkpoint(Request $request, string $domain): array
    {
        abort_unless((bool) $this->gate->allowed($request, $domain), 403);
        $data = $this->payload($request, ['permit', 'sequence', 'window_ms'], [
            'permit' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/D'],
            'sequence' => ['required', 'integer', 'min:1', 'max:100000'],
            'window_ms' => ['required', 'integer', 'min:15000', 'max:16000'],
        ]);
        abort_unless(is_int($data['sequence']) && is_int($data['window_ms']), 422);

        return $this->transaction(function () use ($request, $domain, $data): array {
            $access = $this->gate->allowed($request, $domain, true);
            abort_unless((bool) $access, 403);
            $this->limit($access['grant']->id, 'checkpoint', EstimatedActivityDefinition::CHECKPOINTS_PER_MINUTE);
            $stream = DB::table('estimated_activity_streams')->where('grant_id', $access['grant']->id)
                ->where('token_hash', EstimatedActivityDefinition::fingerprint($data['permit']))->lockForUpdate()->first();
            $now = CarbonImmutable::now('UTC');
            abort_unless($stream && hash_equals($stream->session_hash, $access['binding'])
                && $stream->generation === (int) $access['run']->generation && $stream->definition === EstimatedActivityDefinition::VERSION
                && CarbonImmutable::parse($stream->expires_at, 'UTC')->gt($now), 409, 'observation_binding');
            abort_unless(hash_equals($stream->context_hash, $this->registry->signature($stream->kind, $stream->record_id)), 409, 'context_changed');
            $this->registry->validate($access['principal'], $stream->kind, $stream->record_id);
            $previous = CarbonImmutable::parse($stream->received_at, 'UTC');
            $gap = EstimatedActivityDefinition::micros($now) - EstimatedActivityDefinition::micros($previous);
            abort_unless($gap >= 0 && $gap <= EstimatedActivityDefinition::MAX_GAP_MS * 1000, 409, 'sequence_or_gap');
            $fingerprint = EstimatedActivityDefinition::fingerprint(json_encode([$data['sequence'], $data['window_ms']], JSON_THROW_ON_ERROR));
            $receipt = DB::table('estimated_activity_receipts')->where('stream_id', $stream->id)->where('sequence', $data['sequence'])->lockForUpdate()->first();
            if ($receipt) {
                abort_unless(hash_equals($receipt->fingerprint, $fingerprint), 409);

                return ['sequence' => $receipt->sequence, 'seconds' => $receipt->seconds, 'duplicate' => true];
            }
            abort_unless($data['sequence'] === $stream->sequence + 1, 409, 'sequence_or_gap');
            $seconds = intdiv(min($data['window_ms'] * 1000, $gap, EstimatedActivityDefinition::WINDOW_MS * 1000), 1000000);
            $stamp = EstimatedActivityDefinition::stamp($now);
            DB::table('estimated_activity_receipts')->insert(['stream_id' => $stream->id, 'sequence' => $data['sequence'], 'fingerprint' => $fingerprint, 'seconds' => $seconds, 'received_at' => $stamp]);
            if ($seconds > 0) {
                DB::table('estimated_activity_intervals')->insert($this->evidence($access, $stream->id, $stream->kind, $stream->record_id, $stream->category)
                    + ['sequence' => $data['sequence'], 'started_at' => EstimatedActivityDefinition::stamp($now->subSeconds($seconds)), 'ended_at' => $stamp]);
            }
            DB::table('estimated_activity_streams')->where('id', $stream->id)->update(['sequence' => $data['sequence'], 'received_at' => $stamp]);
            DB::table('estimated_activity_coverage')->where('stream_id', $stream->id)->update(['last_accepted_at' => $stamp]);

            return ['sequence' => $data['sequence'], 'seconds' => $seconds, 'duplicate' => false];
        });
    }

    /** @param list<string> $keys @param array<string,mixed> $rules @return array<string,mixed> */
    private function payload(Request $request, array $keys, array $rules): array
    {
        abort_unless($request->isJson() && strlen($request->getContent()) <= EstimatedActivityDefinition::MAX_PAYLOAD_BYTES, 422);
        $data = $request->json()->all();
        if (array_diff(array_keys($data), $keys) !== []) {
            throw ValidationException::withMessages(['activity' => 'Invalid activity request.']);
        }

        return validator($data, $rules)->validate();
    }

    private function limit(string $grant, string $operation, int $maximum): void
    {
        $key = 'estimated-activity:'.$grant.':'.$operation;
        abort_if(RateLimiter::tooManyAttempts($key, $maximum), 429);
        RateLimiter::hit($key, 60);
    }

    /** Fresh authorization reads after waiting for lifecycle/content locks must not reuse a stale snapshot.
     * Only this collector transaction changes isolation; the original connection setting is restored.
     *
     * @return array<string,mixed> */
    private function transaction(callable $action): array
    {
        if (DB::transactionLevel() > 0) {
            return DB::transaction($action, 5);
        }
        $setting = DB::selectOne("SHOW SESSION VARIABLES LIKE 'transaction_isolation'")
            ?? DB::selectOne("SHOW SESSION VARIABLES LIKE 'tx_isolation'");
        $original = str_replace('-', ' ', strtoupper($setting->Value));
        abort_unless(in_array($original, ['READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE', 'READ UNCOMMITTED'], true), 503);
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            return DB::transaction($action, 5);
        } finally {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL '.$original);
        }
    }

    /** @param array<string,mixed> $access @return array<string,mixed> */
    private function evidence(array $access, string $stream, string $kind, int $record, string $category): array
    {
        return ['run_id' => $access['run']->id, 'grant_id' => $access['grant']->id, 'stream_id' => $stream,
            'student_id' => $access['domain'] === 'student' ? $access['principal']->id : null,
            'administrator_id' => $access['domain'] === 'admin' ? $access['principal']->id : null,
            'workspace_id' => $access['domain'] === 'admin' ? app(TutorWorkWorkspace::class)->ready()->id : null,
            'generation' => $access['run']->generation, 'definition' => EstimatedActivityDefinition::VERSION,
            'kind' => $kind, 'record_id' => $record, 'category' => $category];
    }
}
