<?php

namespace App\Console\Commands;

use App\Domains\Booking\Actions\SyncDiagnosticSessionType;
use Illuminate\Console\Command;

class SyncDiagnosticSessionTypeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:sync-diagnostic-session-type';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize canonical Diagnostic & Learning Roadmap session type and safely deactivate legacy session types';

    /**
     * Execute the console command.
     */
    public function handle(SyncDiagnosticSessionType $action): int
    {
        $sessionType = $action->execute();

        $this->info("Canonical diagnostic session type synced: {$sessionType->title} ({$sessionType->slug}, \${$sessionType->price} USD, {$sessionType->duration_minutes}m, active=".($sessionType->active ? '1' : '0').')');

        return Command::SUCCESS;
    }
}
