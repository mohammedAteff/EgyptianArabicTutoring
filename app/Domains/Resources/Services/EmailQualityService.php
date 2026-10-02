<?php

namespace App\Domains\Resources\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EmailQualityService
{
    public function validate(string $email): void
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (! $ascii || ! filter_var($ascii, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($ascii, '.')) {
            $this->reject('Please enter a valid email address.');
        }
        $domains = file(resource_path('data/disposable-email-domains.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $parts = explode('.', $ascii);
        while (count($parts) > 1) {
            if (in_array(implode('.', $parts), $domains, true)) {
                $this->reject('Please use a permanent email address.');
            }
            array_shift($parts);
        }
        if (Cache::get('email-domain:'.$ascii) === true) {
            return;
        }
        $records = $this->dnsRecords($ascii);
        if ($records === false) {
            $this->reject('We could not check your email right now. Please try again shortly.');
        }
        $mx = array_values(array_filter($records, fn (array $record): bool => ($record['type'] ?? '') === 'MX'));
        $routable = $mx !== []
            ? collect($mx)->contains(fn (array $record): bool => isset($record['target']) && ! in_array($record['target'], ['', '.'], true))
            : collect($records)->contains(fn (array $record): bool => in_array($record['type'] ?? '', ['A', 'AAAA'], true));
        if (! $routable) {
            $this->reject('Please enter a valid email address.');
        }
        Cache::put('email-domain:'.$ascii, true, 3600);
    }

    /** @return list<array<string, mixed>>|false */
    public function dnsRecords(string $domain): array|false
    {
        return @dns_get_record($domain, DNS_MX | DNS_A | DNS_AAAA);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['email' => $message]);
    }
}
