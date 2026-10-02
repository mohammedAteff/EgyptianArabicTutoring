<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Exceptions\StudentIdentityConflictException;
use App\Domains\Students\Models\Student;
use InvalidArgumentException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Normalizer;

class StudentIdentityService
{
    public function normalizeName(string $name): string
    {
        $normalized = Normalizer::normalize($name, Normalizer::FORM_KC);
        if ($normalized === false) {
            throw new InvalidArgumentException('Invalid student name.');
        }

        $lowercase = mb_strtolower($normalized, 'UTF-8');

        return trim((string) preg_replace('/[\p{P}\p{Z}\s]+/u', ' ', $lowercase));
    }

    public function normalizeEmail(?string $email): ?string
    {
        $normalized = mb_strtolower(trim((string) $email), 'UTF-8');

        return $normalized === '' ? null : $normalized;
    }

    public function normalizePhone(?string $phone, ?string $countryCode = null): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }
        if (strlen($raw) > 40) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        if (! str_starts_with($raw, '+') && ! $countryCode) {
            throw new InvalidArgumentException('A country is required for national phone numbers.');
        }

        $region = $countryCode ? strtoupper(trim($countryCode)) : null;
        $util = PhoneNumberUtil::getInstance();
        if ($region && ! in_array($region, $util->getSupportedRegions(), true)) {
            throw new InvalidArgumentException('Invalid phone country.');
        }

        try {
            $parsed = $util->parse($raw, $region);
        } catch (NumberParseException) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        if (! $util->isValidNumber($parsed)) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    public function phoneMatches(?string $input, ?string $canonical): bool
    {
        if (! $input || ! $canonical) {
            return false;
        }
        $util = PhoneNumberUtil::getInstance();
        try {
            $stored = $util->parse($canonical, null);
            $region = $util->getRegionCodeForNumber($stored);
            $raw = trim($input);
            $digits = preg_replace('/[^0-9]/', '', $raw);
            $variants = [$raw, '+'.$digits];
            if (str_starts_with($digits, '00')) {
                $variants[] = '+'.substr($digits, 2);
            }
            foreach (array_unique($variants) as $variant) {
                try {
                    if ($this->normalizePhone($variant, $region) === $canonical) {
                        return true;
                    }
                } catch (InvalidArgumentException) {
                }
            }
        } catch (NumberParseException) {
        }

        return false;
    }

    public function normalizedFullName(string $firstName, string $lastName): string
    {
        return $this->normalizeName($firstName.' '.$lastName);
    }

    public function normalizeIdentity(?string $email, ?string $phone, ?string $firstName, ?string $lastName, ?string $phoneCountry = null): string
    {
        $normalizedEmail = $this->normalizeEmail($email) ?? '';
        try {
            $normalizedPhone = $this->normalizePhone($phone, $phoneCountry) ?? '';
        } catch (\Throwable) {
            $normalizedPhone = mb_strtolower(trim((string) $phone), 'UTF-8');
        }
        $normalizedName = $this->normalizedFullName((string) $firstName, (string) $lastName);

        return "{$normalizedEmail}|{$normalizedPhone}|{$normalizedName}";
    }

    /** @return array<string, string> */
    public function authFingerprints(?string $email, ?string $phone, ?string $dateOfBirth, ?string $phoneCountry = null): array
    {
        $email = $this->normalizeEmail($email);
        try {
            $phone = $this->normalizePhone($phone, $phoneCountry);
        } catch (InvalidArgumentException) {
            $phone = substr(trim((string) $phone), 0, 80) ?: null;
        }

        $secret = (string) (config('services.student_auth.hmac_key') ?: config('app.key'));
        if ($secret === '') {
            throw new InvalidArgumentException('Student authentication secret is not configured.');
        }

        $fingerprints = [];
        foreach (['email' => $email, 'phone' => $phone] as $field => $value) {
            if ($value === null) {
                continue;
            }

            $fingerprints[$field] = $field.':'.hash_hmac('sha256', $value, $secret);
            if ($dateOfBirth !== null && $dateOfBirth !== '') {
                $fingerprints['dob_'.$field] = 'dob_'.$field.':'.hash_hmac('sha256', substr($dateOfBirth, 0, 32).'|'.$value, $secret);
            }
        }

        return $fingerprints;
    }

    public function resolveCanonicalStudent(Student $student, bool $lock = false): Student
    {
        $visited = [$student->id];
        $current = $student;

        while ($current->identity_status === 'merged') {
            if ($current->merged_into_student_id === null) {
                throw new StudentIdentityConflictException('The student merge chain is incomplete.');
            }
            $nextId = (int) $current->merged_into_student_id;
            if (in_array($nextId, $visited, true)) {
                throw new StudentIdentityConflictException('The student merge chain contains a cycle.');
            }
            $visited[] = $nextId;
            $query = Student::withTrashed()->whereKey($nextId);
            if ($lock) {
                $query->lockForUpdate();
            }
            $target = $query->first();
            if (! $target) {
                throw new StudentIdentityConflictException('The student merge chain is incomplete.');
            }
            $current = $target;
        }

        return $current;
    }
}
