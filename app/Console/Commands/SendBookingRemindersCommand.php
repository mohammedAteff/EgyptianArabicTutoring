<?php

namespace App\Console\Commands;

use App\Domains\Booking\Models\Booking;
use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBookingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:send-telegram-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send multi-recipient Telegram lesson reminders for confirmed bookings across dynamic admin-configured lead time windows';

    public function handle(TelegramNotificationService $telegramService): int
    {
        if (filter_var(Setting::get('telegram.center_migrated', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->info('Reminders are managed by Telegram Bots; forwarding to telegram:tick.');
            $this->call('telegram:tick');

            return self::SUCCESS;
        }

        $enabled = Setting::get('telegram.reminders_enabled', false);
        if (! in_array($enabled, [true, 1, '1', 'true'], true)) {
            $this->info('Telegram reminders are currently disabled in Settings.');

            return self::SUCCESS;
        }

        $chatIds = Setting::get('telegram.notification_chat_ids', []);
        if (is_string($chatIds)) {
            $chatIds = json_decode($chatIds, true) ?: array_filter(array_map('trim', explode(',', $chatIds)));
        }

        if (empty($chatIds)) {
            $this->info('No recipient Telegram Chat IDs are configured in Settings.');

            return self::SUCCESS;
        }

        $windows = Setting::get('telegram.reminder_windows', []);
        if (is_string($windows)) {
            $windows = json_decode($windows, true) ?: array_filter(array_map('intval', explode(',', $windows)));
        }

        if (empty($windows)) {
            $this->info('No Telegram reminder milestones are configured in Settings.');

            return self::SUCCESS;
        }

        $now = Carbon::now('UTC');
        $dispatchedCount = 0;

        foreach ($windows as $windowMinutes) {
            $windowMinutes = (int) $windowMinutes;
            if ($windowMinutes <= 0) {
                continue;
            }

            $horizon = $now->copy()->addMinutes($windowMinutes);

            // Confirmed bookings starting within the horizon window that have not started yet
            $bookings = Booking::query()
                ->where('status', 'confirmed')
                ->where('start_at_utc', '<=', $horizon)
                ->where('start_at_utc', '>', $now)
                ->orderBy('start_at_utc', 'asc')
                ->get();

            foreach ($bookings as $booking) {
                foreach ($chatIds as $chatId) {
                    $chatId = trim((string) $chatId);
                    if ($chatId === '') {
                        continue;
                    }

                    $sent = $telegramService->sendBookingReminderToRecipient($booking, $chatId, $windowMinutes);
                    if ($sent) {
                        $dispatchedCount++;
                    }
                }
            }
        }

        $this->info("Telegram reminders check completed. Processed {$dispatchedCount} reminders.");

        return self::SUCCESS;
    }
}
