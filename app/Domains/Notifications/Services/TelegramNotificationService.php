<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Send a lesson reminder to a specific Telegram chat ID for a given window milestone.
     */
    public function sendBookingReminderToRecipient(Booking $booking, string $chatId, int $windowMinutes): bool
    {
        $encryptedToken = Setting::get('telegram.bot_token');
        if (empty($encryptedToken)) {
            Log::warning('Telegram reminder skipped: telegram.bot_token is not configured.');

            return false;
        }

        try {
            $token = Crypt::decryptString($encryptedToken);
        } catch (\Throwable) {
            Log::warning('Telegram reminder skipped: the stored bot token could not be decrypted.');

            return false;
        }

        $message = $this->formatReminderMessage($booking, $windowMinutes);
        $dedupeKey = "reminder_{$booking->id}_{$windowMinutes}m_{$chatId}";

        // 1. Check if recipient already received this specific milestone reminder
        if (DB::table('booking_events')->where('idempotency_key', $dedupeKey)->exists()) {
            return true;
        }

        // 2. Dispatch sequentially with strict 5-second timeout
        try {
            $response = Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ])
                ->throw();

            if ($response->json('ok') !== true) {
                Log::warning('Telegram rejected a reminder.', ['error_code' => (int) $response->json('error_code', 0)]);

                return false;
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram reminder delivery failed.', ['exception_type' => $e::class]);

            return false;
        }

        // 3. Record success under compound idempotency key
        try {
            DB::table('booking_events')->insert([
                'booking_id' => $booking->id,
                'event_type' => 'telegram_reminder_sent',
                'idempotency_key' => $dedupeKey,
                'performed_by' => 'system',
                'new_data' => json_encode([
                    'chat_id' => $chatId,
                    'window_minutes' => $windowMinutes,
                    'accepted_at' => now()->toIso8601String(),
                ]),
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Handled concurrently by another process
        }

        return true;
    }

    /**
     * Send a test connectivity reminder to verify bot credentials and chat ID.
     */
    public function sendTestNotification(string $chatId): bool
    {
        $encryptedToken = Setting::get('telegram.bot_token');
        if (empty($encryptedToken)) {
            return false;
        }

        try {
            $token = Crypt::decryptString($encryptedToken);
        } catch (\Throwable) {
            Log::warning('Telegram test notification skipped: the stored bot token could not be decrypted.');

            return false;
        }

        $cairoNow = Carbon::now('Africa/Cairo')->format('g:i A (T), M j, Y');
        $message = "🤖 *Telegram Bot Test Alert*\n\n"
            ."Arabic Tutoring notification bot is connected and verified.\n"
            ."🕒 *Server Time:* `{$cairoNow}`\n"
            .'Status: Ready to dispatch student lesson reminders.';

        try {
            $response = Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ])
                ->throw();

            return $response->json('ok') === true;
        } catch (\Throwable $e) {
            Log::warning('Telegram test notification failed.', ['exception_type' => $e::class]);

            return false;
        }
    }

    /**
     * Format lesson reminder Markdown copy.
     */
    public function formatReminderMessage(Booking $booking, int $windowMinutes): string
    {
        $booking->loadMissing(['contact', 'student', 'sessionType']);

        $studentName = $booking->student?->name
            ?: ($booking->contact->name ?? 'Student');
        $studentEmail = $booking->student?->email ?: ($booking->contact?->email ?? 'N/A');
        $studentPhone = $booking->student?->phone ?: ($booking->contact?->phone ?? 'N/A');
        $sessionTitle = $booking->sessionType?->title ?: '1-on-1 Egyptian Arabic Session';

        $startUtc = Carbon::parse($booking->start_at_utc, 'UTC');
        $startBusiness = $startUtc->copy()->setTimezone(app(TimezoneService::class)->getBusinessTimezone());

        $windowLabel = $this->humanizeMinutes($windowMinutes);
        $meetingUrl = app(MeetingLinkService::class)->notificationUrl($booking, $windowMinutes) ?? '';
        $meetingLine = filter_var($meetingUrl, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($meetingUrl, PHP_URL_SCHEME)) === 'https'
                ? "🔗 *Classroom Room:* [Join Session]({$meetingUrl})"
                : '🔗 *Classroom Room:* Meeting link will be shared separately.';

        return "🔔 *Lesson Reminder: Starting {$windowLabel}*\n\n"
            ."👤 *Student:* {$studentName}\n"
            ."📧 *Email:* `{$studentEmail}`\n"
            ."📞 *Phone:* `{$studentPhone}`\n"
            ."📚 *Track:* {$sessionTitle}\n"
            ."🕒 *Tutor Time:* `{$startBusiness->format('h:i A (l, M j)')}` ({$startBusiness->timezoneName})\n"
            ."🌐 *UTC Time:* `{$startUtc->format('H:i (Y-m-d)')}`\n"
            .$meetingLine;
    }

    /**
     * Convert minutes into human-readable label.
     */
    protected function humanizeMinutes(int $minutes): string
    {
        if ($minutes >= 1440) {
            $days = round($minutes / 1440, 1);

            return "in {$days} ".($days == 1 ? 'day' : 'days');
        }

        if ($minutes >= 60) {
            $hours = round($minutes / 60, 1);

            return "in {$hours} ".($hours == 1 ? 'hour' : 'hours');
        }

        return "in {$minutes} minutes";
    }
}
