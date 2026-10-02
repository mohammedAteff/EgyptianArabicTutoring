<?php

namespace App\Console\Commands;

use App\Domains\Notifications\Services\TelegramLegacyImporter;
use Illuminate\Console\Command;

class ImportTelegramLegacyCommand extends Command
{
    protected $signature = 'telegram:import-legacy';

    protected $description = 'Import encrypted legacy reminders once without changing public social links.';

    public function handle(): int
    {
        app(TelegramLegacyImporter::class)->import();
        $this->info('Legacy Telegram configuration imported or already migrated.');

        return self::SUCCESS;
    }
}
