<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('event_uuid')->nullable()->unique('audit_event_uuid_unique');
            $table->string('actor_type', 16)->default('admin');
            $table->foreignId('actor_user_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->foreignId('actor_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('target_type', 120)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
        });

        $secret = (string) (config('services.student_auth.hmac_key') ?: config('app.key'));
        if ($secret === '') {
            throw new RuntimeException('Cannot safely pseudonymize historical audit payloads without an application HMAC key.');
        }

        DB::table('audit_logs')->orderBy('id')->chunkById(200, function ($logs) use ($secret): void {
            foreach ($logs as $log) {
                $oldValues = $this->scrubPayload($log->previous_data, $secret);
                $newValues = $this->scrubPayload($log->new_data, $secret);
                $oldJson = $oldValues === null ? null : json_encode($oldValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $newJson = $newValues === null ? null : json_encode($newValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                DB::table('audit_logs')->where('id', $log->id)->update([
                    'event_uuid' => (string) Str::uuid(),
                    'actor_user_id' => $log->administrator_id,
                    'actor_type' => $log->administrator_id === null ? 'system' : 'admin',
                    'target_type' => $log->entity_type,
                    'target_id' => $log->entity_id === null ? null : (string) $log->entity_id,
                    'previous_data' => $oldJson,
                    'new_data' => $newJson,
                    'old_values' => $oldJson,
                    'new_values' => $newJson,
                    'created_at' => $log->created_at,
                ]);
            }
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('event_uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('audit_logs')->exists()) {
            throw new RuntimeException('Cannot remove append-only audit event identity or student actor history.');
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_id']);
            $table->dropForeign(['actor_student_id']);
            $table->dropUnique('audit_event_uuid_unique');
            $table->dropColumn(['event_uuid', 'actor_type', 'actor_user_id', 'actor_student_id', 'target_type', 'target_id', 'old_values', 'new_values']);
        });
    }

    /** @return array<mixed>|null */
    private function scrubPayload(mixed $json, string $secret): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $payload = is_array($json) ? $json : json_decode((string) $json, true);
        if (! is_array($payload)) {
            return ['redacted_payload' => '[redacted:'.hash_hmac('sha256', (string) $json, $secret).']'];
        }

        $scrub = function (mixed $value, ?string $key = null) use (&$scrub, $secret): mixed {
            $normalizedKey = $key === null ? '' : strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $key), '_'));
            if ($normalizedKey !== '' && preg_match('/(?:^|_)(?:email|phone|birth|dob|date_of_birth|password|remember_token|session_id|[a-z0-9]*token|first_name|last_name|full_name|name|note|notes|transaction_reference|bank_account|card_number)(?:_|$)/i', $normalizedKey)) {
                return '[redacted:'.hash_hmac('sha256', (string) json_encode($value, JSON_UNESCAPED_UNICODE), $secret).']';
            }

            if (is_array($value)) {
                $result = [];
                foreach ($value as $childKey => $childValue) {
                    $result[$childKey] = $scrub($childValue, is_string($childKey) ? $childKey : null);
                }

                return $result;
            }

            if (! is_string($value)) {
                return $value;
            }

            $value = preg_replace_callback('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', fn (array $match): string => '[redacted:'.hash_hmac('sha256', $match[0], $secret).']', $value) ?? $value;

            return preg_replace_callback('/\+?[0-9][0-9\s().-]{7,18}[0-9]/', fn (array $match): string => '[redacted:'.hash_hmac('sha256', $match[0], $secret).']', $value) ?? $value;
        };

        return $scrub($payload);
    }
};
