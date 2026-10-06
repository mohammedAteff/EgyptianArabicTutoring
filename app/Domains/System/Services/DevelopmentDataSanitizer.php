<?php

namespace App\Domains\System\Services;

class DevelopmentDataSanitizer
{
    public function __construct(private DevelopmentDataSchema $schema) {}

    /** @param array<string, mixed> $row
     * @return array<string, mixed> */
    public function row(array $row, ?string $table = null): array
    {
        if ($table !== null) {
            foreach ($this->schema->read()[$table]['columns'] as $column => $metadata) {
                if (! empty($metadata['generated']) || ! empty($metadata['generation'])) {
                    unset($row[$column]);
                }
            }
        }
        foreach ($row as $key => $value) {
            if ($this->secret((string) $key)) {
                unset($row[$key]);
            } elseif (is_array($value)) {
                $row[$key] = $this->row($value);
            } elseif (is_string($value) && in_array($key, ['previous_data', 'new_data', 'snapshot', 'snapshot_answers', 'metadata'], true)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $row[$key] = json_encode($this->row($decoded), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }
            }
        }

        return $row;
    }

    public function secret(string $key): bool
    {
        return preg_match('/password|secret|recovery.?code|app.?key|confirmation_token|hold_token|token_hash|remember_token|verification.?code|consumed_challenge_hash/i', $key) === 1;
    }

    /** @param array<string, mixed> $row */
    public function fingerprint(array $row, ?string $table = null): string
    {
        $row = $this->row($row, $table);
        unset($row['path'], $row['file_path'], $row['disk']);
        ksort($row);
        foreach ($row as $key => $value) {
            if ($value !== null && is_scalar($value)) {
                $row[$key] = (string) $value;
            }
        }

        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
