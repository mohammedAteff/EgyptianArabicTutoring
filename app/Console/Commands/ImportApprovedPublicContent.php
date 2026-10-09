<?php

namespace App\Console\Commands;

use App\Domains\CMS\Services\PublicContentImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Signature('content:import-approved {--dry-run : Show the approved local changes without writing them}')]
#[Description('Apply the approved English public copy and QA visibility changes to a local review dataset')]
class ImportApprovedPublicContent extends Command
{
    public function handle(PublicContentImporter $importer): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is restricted to local and testing environments.');

            return self::FAILURE;
        }
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            DB::beginTransaction();
        }
        try {
            $receipt = $importer->import();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }
        if ($dryRun) {
            $receipt['dry_run'] = true;
        }
        $this->line(json_encode($receipt, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
