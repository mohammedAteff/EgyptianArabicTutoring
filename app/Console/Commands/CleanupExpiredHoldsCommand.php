<?php

namespace App\Console\Commands;

use App\Domains\Booking\Services\BookingHoldService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
    protected $description = 'Clean up expired booking holds and prune historical records older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle(BookingHoldService $holdService): int
    {
        $expiredCount = $holdService->cleanExpiredHolds();
        $this->info("Cleaned up {$expiredCount} expired booking hold(s).");

        $pruned = DB::table('booking_holds')
            ->whereIn('status', ['expired', 'released'])
            ->where('expires_at', '<', now()->subDays(30))
            ->orderBy('id')
            ->limit(1000)
            ->delete();

        if ($pruned > 0) {
            $this->info("Pruned {$pruned} historical booking hold(s) older than 30 days.");
        }

        return self::SUCCESS;
    }
}
