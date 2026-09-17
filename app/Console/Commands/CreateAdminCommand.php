<?php

namespace App\Console\Commands;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create 
                            {email? : The administrator email address}
                            {--name= : Full name of the administrator}
                            {--role=admin : Role of the administrator (super_admin or admin)}
                            {--password= : Optional password; if not provided, a secure one will be generated or prompted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a new administrator safely from the command line';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Administrator Email Address');
        $name = $this->option('name') ?: $this->ask('Administrator Full Name');
        $role = $this->option('role');

        if (! in_array($role, ['super_admin', 'admin'], true)) {
            $role = $this->choice('Select Administrator Role', ['admin', 'super_admin'], 0);
        }

        $password = $this->option('password');
        $wasPasswordGenerated = false;

        if (! $password) {
            if ($this->input->isInteractive()) {
                $password = $this->secret('Enter Password (leave blank to generate random secure password)');
            }
            if (! $password) {
                $password = Str::random(20);
                $wasPasswordGenerated = true;
            }
        }

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'unique:administrators,email'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:super_admin,admin'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Administrator::create([
            'name' => $name,
            'email' => strtolower(trim($email)),
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        AuditLog::create([
            'administrator_id' => null,
            'action' => 'administrator_cli_created',
            'entity_type' => Administrator::class,
            'entity_id' => $admin->id,
            'new_data' => [
                'email' => $admin->email,
                'name' => $admin->name,
                'role' => $admin->role,
            ],
            'created_at' => now(),
        ]);

        $this->info("Administrator [{$admin->email}] successfully created with role [{$admin->role}].");
        if ($wasPasswordGenerated) {
            $this->warn("Temporary generated password: {$password}");
            $this->warn('Please store this credential safely and change it upon first login.');
        }

        return self::SUCCESS;
    }
}
