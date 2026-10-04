<?php

namespace App\Console\Commands;

use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\EntitlementMappingService;
use Illuminate\Console\Command;
use Throwable;

class ReviewEntitlementMapping extends Command
{
    protected $signature = 'entitlements:review {manifest? : Reviewed JSON manifest} {--apply : Apply the reviewed classification}';

    protected $description = 'Inventory or explicitly review historical entitlements without inferring rights';

    public function handle(EntitlementMappingService $mapping): int
    {
        try {
            $path = $this->argument('manifest');
            if ($path) {
                $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                $this->line(json_encode($mapping->review($manifest, (bool) $this->option('apply')), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                if ($this->option('apply')) {
                    $this->error('An explicit reviewed manifest is required.');

                    return self::FAILURE;
                }
                foreach (StudentPackage::query()->where('identity_state', 'legacy_unclassified')->orderBy('id')->get() as $package) {
                    $snapshot = $mapping->snapshot($package->id);
                    unset($snapshot['facts']['package']['student_id']);
                    foreach (['entries', 'bookings'] as $kind) {
                        foreach ($snapshot['facts'][$kind] as &$fact) {
                            unset($fact['student_id']);
                            if (isset($fact['description'])) {
                                $fact['description_hash'] = hash('sha256', $fact['description']);
                                unset($fact['description']);
                            }
                        } unset($fact);
                    }
                    $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR));
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
