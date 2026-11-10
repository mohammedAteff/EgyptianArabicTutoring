<?php

namespace App\Console\Commands;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Forms\Services\FinalStudentFormsInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class InstallStudentFormsCommand extends Command
{
    protected $signature = 'forms:install-onboarding {--administrator= : Existing authorized author ID} {--dry-run : Report without writing} {--replace-final : Replace canonical forms with the final questionnaires}';

    protected $description = 'Install the final student questionnaires; preserve existing forms unless --replace-final is supplied';

    public function handle(FinalStudentFormsInstaller $installer): int
    {
        try {
            $target = $installer->target();
            $this->line(json_encode(['target' => $target], JSON_THROW_ON_ERROR));
            $author = Administrator::query()->whereNull('suspended_at')->whereIn('role', ['super_admin', 'admin'])
                ->when($this->option('administrator') !== null, fn ($query) => $query->whereKey($this->option('administrator')))->orderBy('id')->first();
            if (! $author) {
                $this->error('An existing active administrator is required to author onboarding forms.');

                return self::FAILURE;
            }
            $replace = (bool) $this->option('replace-final');
            $plans = $installer->plan($replace);
            $this->line(json_encode(['author_id' => $author->id, 'plan' => $plans], JSON_THROW_ON_ERROR));
            if ($this->option('dry-run')) {
                $this->info('Dry run complete. No changes made.');

                return self::SUCCESS;
            }
            if (collect($plans)->contains(fn (array $plan): bool => in_array($plan['action'], ['create', 'replace'], true))) {
                $directory = storage_path('app/private/forms-intake-'.now()->format('Ymd'));
                File::ensureDirectoryExists($directory, 0700);
                $snapshotPath = $directory.'/definitions-before-'.now()->format('Ymd-His-u').'.json';
                File::put($snapshotPath, json_encode($installer->snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $this->line('Recoverable definition snapshot: '.$snapshotPath);
                $this->line(json_encode(['result' => $installer->apply($author, $replace, $plans)], JSON_THROW_ON_ERROR));
            } else {
                $this->info('No changes. Existing definitions preserved.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('No replacement committed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
