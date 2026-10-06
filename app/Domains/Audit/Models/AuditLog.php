<?php

namespace App\Domains\Audit\Models;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'administrator_id',
        'event_uuid',
        'actor_type',
        'actor_user_id',
        'actor_student_id',
        'action',
        'entity_type',
        'entity_id',
        'target_type',
        'target_id',
        'previous_data',
        'new_data',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_data' => 'array',
            'new_data' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $audit): void {
            $audit->ip_address = null;
            $audit->event_uuid ??= (string) Str::uuid();
            $audit->actor_user_id ??= $audit->administrator_id;
            $audit->actor_type ??= $audit->actor_student_id ? 'student' : ($audit->actor_user_id ? 'admin' : 'system');
            $audit->target_type ??= $audit->entity_type;
            $audit->target_id ??= $audit->entity_id === null ? null : (string) $audit->entity_id;

            $oldValues = self::scrubPayload($audit->old_values ?? $audit->previous_data);
            $newValues = self::scrubPayload($audit->new_values ?? $audit->new_data);
            $audit->old_values = $oldValues;
            $audit->new_values = $newValues;
            $audit->previous_data = $oldValues;
            $audit->new_data = $newValues;
        });
        static::updating(fn (): never => throw new RuntimeException('Audit rows are append-only; use the privacy redaction workflow for payload scrubbing.'));
        static::deleting(fn (): never => throw new RuntimeException('Audit rows are append-only.'));
    }

    /** @return array<mixed>|null */
    public static function scrubPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }
        $secret = (string) (config('services.student_auth.hmac_key') ?: config('app.key'));

        $scrub = function (mixed $value, ?string $key = null) use (&$scrub, $secret): mixed {
            if (is_array($value)) {
                $result = [];
                foreach ($value as $childKey => $childValue) {
                    $keyString = is_string($childKey) ? $childKey : null;
                    $normalizedKey = $keyString === null ? '' : strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $keyString), '_'));
                    if (in_array($normalizedKey, ['ip', 'ip_address', 'client_ip', 'request_ip', 'remote_addr', 'remote_address'], true)) {
                        $result[$childKey] = '[redacted]';

                        continue;
                    }
                    if ($normalizedKey !== '' && preg_match('/(?:^|_)(?:email|phone|birth|dob|date_of_birth|password|secret|totp|otp|two_factor|recovery_codes|verification_code|remember_token|session_id|[a-z0-9]*token|first_name|last_name|full_name|name|note|notes|transaction_reference|bank_account|card_number)(?:_|$)/i', $normalizedKey)) {
                        $result[$childKey] = self::blind((string) json_encode($childValue, JSON_UNESCAPED_UNICODE), $secret);
                    } else {
                        $result[$childKey] = $scrub($childValue, $keyString);
                    }
                }

                return $result;
            }
            if (! is_string($value)) {
                return $value;
            }

            if (filter_var(trim($value), FILTER_VALIDATE_IP)) {
                return '[redacted]';
            }
            $value = preg_replace_callback('/(?<![\w.])(?:\d{1,3}\.){3}\d{1,3}(?![\w.])/', fn (array $match): string => filter_var($match[0], FILTER_VALIDATE_IP) ? '[redacted]' : $match[0], $value) ?? $value;
            $value = preg_replace_callback('/(?<![\w:])(?:[a-f0-9]{0,4}:){2,}[a-f0-9:.]+(?![\w:])/i', fn (array $match): string => filter_var($match[0], FILTER_VALIDATE_IP) ? '[redacted]' : $match[0], $value) ?? $value;

            if ($key === 'expiration_date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }

            $value = preg_replace_callback('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', fn (array $matches): string => self::blind($matches[0], $secret), $value) ?? $value;
            $value = preg_replace_callback('/\+?[0-9][0-9\s().-]{7,18}[0-9]/', fn (array $matches): string => self::blind($matches[0], $secret), $value) ?? $value;

            return $value;
        };

        return $scrub($payload);
    }

    private static function blind(string $value, string $secret): string
    {
        return '[redacted:'.hash_hmac('sha256', $value, $secret).']';
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'administrator_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'actor_user_id');
    }

    public function actorStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'actor_student_id');
    }
}
