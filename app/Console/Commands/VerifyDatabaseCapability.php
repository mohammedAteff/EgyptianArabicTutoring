<?php

namespace App\Console\Commands;

use App\Domains\Database\Services\DatabaseCapability;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('db:verify-capability')]
#[Description('Report the connected database and verify the exact reconciled production vendor and release.')]
class VerifyDatabaseCapability extends Command
{
    public function handle(): int
    {
        $capability = new DatabaseCapability(deferReconciledCheck: true);
        $this->line('Detected vendor: '.$capability->vendor());
        $this->line('Detected server version: '.$capability->version());
        $this->line('Laravel connection driver: '.$capability->driver());

        $passed = true;
        try {
            $capability->assertMatchesReconciledEnvironment(
                config('database.reconciled.vendor'),
                config('database.reconciled.version'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            $passed = false;
        }

        if (in_array($capability->vendor(), ['mysql', 'mariadb'], true)) {
            $hasStudentForeignKey = DB::table('information_schema.key_column_usage')
                ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'bookings')
                ->where('COLUMN_NAME', 'student_id')
                ->where('REFERENCED_TABLE_NAME', 'students')
                ->where('REFERENCED_COLUMN_NAME', 'id')
                ->exists();

            if (! $hasStudentForeignKey) {
                $this->error('Required bookings.student_id -> students.id foreign key is not installed.');
                $this->warn('Migration 8B intentionally refuses blocking DDL. Confirm a restorable backup and zero orphans, then use a reviewed online-schema-change plan or an approved maintenance window. Do not disable foreign_key_checks.');
                $passed = false;
            }
        } else {
            $this->error('The booking-to-student foreign-key deployment has not been verified for this database vendor.');
            $passed = false;
        }

        if ($passed) {
            $this->info('Database release and required booking-to-student referential integrity are verified.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
