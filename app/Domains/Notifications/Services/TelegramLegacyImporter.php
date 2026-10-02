<?php

namespace App\Domains\Notifications\Services;

use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramBot;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Notifications\Models\TelegramRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TelegramLegacyImporter
{
    public function __construct(private TelegramRuleCatalog $catalog) {}

    public function import(): void
    {
        if (Setting::get('telegram.center_migrated', false)) {
            return;
        }
        Cache::lock('telegram-legacy-import', 30)->block(5, function (): void {
            DB::transaction(function (): void {
                if (Setting::get('telegram.center_migrated', false)) {
                    return;
                }
                $encrypted = Setting::get('telegram.bot_token');
                if (is_string($encrypted) && $encrypted !== '') {
                    $token = Crypt::decryptString($encrypted);
                    $enabled = filter_var(Setting::get('telegram.reminders_enabled', false), FILTER_VALIDATE_BOOLEAN);
                    $bot = TelegramBot::create(['name' => 'Existing Reminder Bot', 'token' => $token, 'enabled' => true]);
                    $chats = Setting::get('telegram.notification_chat_ids', []);
                    if (! is_array($chats)) {
                        $chats = array_filter(explode(',', (string) $chats));
                    }
                    $ids = [];
                    foreach (array_unique($chats) as $chat) {
                        $dest = TelegramDestination::create(['telegram_bot_id' => $bot->id, 'name' => 'Existing reminder destination', 'chat_id' => trim((string) $chat), 'detail_level' => 'personal']);
                        $ids[] = $dest->id;
                    }
                    $windows = Setting::get('telegram.reminder_windows', [1440, 60]);
                    if (! is_array($windows)) {
                        $windows = array_filter(explode(',', (string) $windows));
                    }
                    foreach (array_unique(array_map('intval', $windows)) as $minutes) {
                        if ($minutes < 1) {
                            continue;
                        }
                        $rule = TelegramRule::create(['telegram_bot_id' => $bot->id, 'name' => 'Existing '.$minutes.' minute reminder', 'trigger' => 'session_reminder', 'legacy_reminder' => true, 'enabled' => $enabled, 'minutes' => $minutes, 'cooldown_minutes' => 0, 'template' => $this->catalog->get('session_reminder')['template']]);
                        $rule->destinations()->sync($ids);
                    }
                    Setting::set('telegram.automation_enabled', $enabled, 'telegram');
                }
                Setting::set('telegram.center_migrated', true, 'telegram');
            });
        });
    }
}
