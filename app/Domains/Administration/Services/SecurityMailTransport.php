<?php

namespace App\Domains\Administration\Services;

class SecurityMailTransport
{
    /** @param list<string> $seen */
    public static function canDeliver(?string $mailer = null, array $seen = []): bool
    {
        $mailer ??= config('mail.default');
        if (! is_string($mailer) || in_array($mailer, $seen, true)) {
            return false;
        }
        $definition = config('mail.mailers.'.$mailer, []);
        $transport = $definition['transport'] ?? null;
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $definition['mailers'] ?? [];
            if ($children === []) {
                return false;
            }
            foreach ($children as $child) {
                if (! is_string($child) || ! self::canDeliver($child, [...$seen, $mailer])) {
                    return false;
                }
            }

            return true;
        }

        return in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true);
    }
}
