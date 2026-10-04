<?php

namespace App\Console\Commands;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use Illuminate\Console\Command;

class RecoverAdministratorTwoFactor extends Command
{
    protected $signature = 'administrator:recover-two-factor {administrator : Exact Super Admin ID} {--operator= : Verified recovery operator} {--reason= : Recovery ticket or reason without secrets}';

    protected $description = 'Perform explicitly confirmed, trusted-console Super Admin two-factor recovery';

    public function handle(AdministratorTwoFactorService $twoFactor): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Emergency recovery requires an interactive trusted console.');

            return self::FAILURE;
        }
        $id = $this->argument('administrator');
        $operator = trim((string) $this->option('operator'));
        $reason = trim((string) $this->option('reason'));
        if (! ctype_digit((string) $id) || $operator === '' || $reason === '' || strlen($operator) > 100 || strlen($reason) > 500
            || ! Administrator::query()->whereKey((int) $id)->where('role', 'super_admin')->exists()) {
            $this->error('Supply an exact Super Admin ID, operator and non-secret recovery reason.');

            return self::FAILURE;
        }
        $this->warn('This clears two-factor state and revokes sessions. Suspension and password remain unchanged.');
        if ($this->ask('Type RECOVER '.$id.' to confirm') !== 'RECOVER '.$id) {
            $this->info('Recovery cancelled.');

            return self::FAILURE;
        }
        $twoFactor->emergencyRecover((int) $id, $operator, $reason);
        $this->info('Security recovery recorded. The owner must sign in and enroll a new authenticator.');

        return self::SUCCESS;
    }
}
