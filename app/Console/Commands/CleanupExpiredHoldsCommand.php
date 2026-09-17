<?php

namespace App\Console\Commands;

use App\Domains\Booking\Services\BookingHoldService;
use Illuminate\Console\Command;

class CleanupExpiredHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:cleanup-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired booking holds and update their status to expired';

    /**
     * Execute the console command.
     */
    public function handle(BookingHoldService $holdService): int
    {
        $expiredCount = $holdService->cleanExpiredHolds();
        $this->info("Cleaned up {$expiredCount} expired booking hold(s).");

        return self::SUCCESS;
    }
}
