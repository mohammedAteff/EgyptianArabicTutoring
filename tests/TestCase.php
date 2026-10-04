<?php

namespace Tests;

use App\Domains\Booking\Models\SessionType;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\EntitlementType;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

if (! class_exists('Symfony\Component\DomCrawler\Crawler')) {
    require_once __DIR__.'/Support/Crawler.php';
}

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits(): array
    {
        app(DatabaseCapability::class)->assertMatchesReconciledEnvironment(
            config('database.reconciled.vendor'),
            config('database.reconciled.version'),
        );

        return parent::setUpTraits();
    }

    protected function oneHourPackageLesson(): SessionType
    {
        return SessionType::query()->firstOrCreate(['slug' => 'explicit-one-hour-package-fixture'], ['title' => 'One-hour package fixture', 'duration_minutes' => 60, 'price' => '25.00', 'currency' => 'USD', 'active' => true, 'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
    }
}
