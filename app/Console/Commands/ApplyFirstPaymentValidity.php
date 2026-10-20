<?php

namespace App\Console\Commands;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\PackageValidityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyFirstPaymentValidity extends Command
{
    protected $signature = 'students:apply-first-payment-validity {--apply} {--administrator=}';

    protected $description = 'Preview or apply the approved first-payment validity policy to existing coaching purchases';

    public function handle(PackageValidityService $validity, DatabaseCapability $database, AuditLogService $audit): int
    {
        $actor = null;
        if ($this->option('apply')) {
            $actor = Administrator::query()->whereKey($this->option('administrator'))->where('role', 'super_admin')->whereNull('suspended_at')->firstOrFail();
        }
        $changes = [];
        $database->transaction(function () use ($validity, $audit, $actor, &$changes): void {
            $packages = StudentPackage::query()->whereNotNull('validity_days')->whereHas('payments')->orderBy('id')->lockForUpdate()->get();
            foreach ($packages as $package) {
                $after = $validity->firstPaymentTerms($package);
                if ($after === []) {
                    continue;
                }
                $before = $package->only(array_keys($after));
                $changes[] = ['purchase_id' => $package->id, 'before' => $before, 'after' => $after];
                if ($actor) {
                    DB::table('student_packages')->where('id', $package->id)->update($after);
                    $audit->log('package_first_payment_policy_applied', StudentPackage::class, $package->id, $before, $after, $actor->id);
                }
            }
        }, 3);
        $this->line(json_encode(['applied' => $actor !== null, 'changes' => $changes], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
