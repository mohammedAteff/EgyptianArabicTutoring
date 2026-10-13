<?php

namespace Tests;

use App\Domains\Booking\Models\SessionType;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\EntitlementType;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

if (! class_exists('Symfony\Component\DomCrawler\Crawler')) {
    require_once __DIR__.'/Support/Crawler.php';
}

abstract class TestCase extends BaseTestCase
{
    private static ?string $databaseStrategy = null;

    protected function setUpTraits(): array
    {
        $connection = DB::connection();

        if (! app()->environment('testing')
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'bolt_landing_test'
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost'], true)
            || (string) $connection->getConfig('port') !== '3307') {
            throw new RuntimeException('Automated tests require the dedicated local bolt_landing_test database on port 3307. No refresh or test setup was run.');
        }

        if ($connection->selectOne('SELECT DATABASE() AS database_name')->database_name !== 'bolt_landing_test') {
            throw new RuntimeException('The active database is not bolt_landing_test. No refresh or test setup was run.');
        }

        app(DatabaseCapability::class)->assertMatchesReconciledEnvironment(
            config('database.reconciled.vendor'),
            config('database.reconciled.version'),
        );

        $traits = class_uses_recursive(static::class);
        $strategy = isset($traits[DatabaseTruncation::class]) ? 'committed:'.static::class
            : (isset($traits[RefreshDatabase::class]) ? 'transactional' : null);
        if ($strategy !== null && self::$databaseStrategy !== $strategy) {
            // Committed concurrency fixtures must not survive into transactional tests or another committed class.
            RefreshDatabaseState::$migrated = false;
            self::$databaseStrategy = $strategy;
        }

        return parent::setUpTraits();
    }

    protected function oneHourPackageLesson(): SessionType
    {
        return SessionType::query()->firstOrCreate(['slug' => 'explicit-one-hour-package-fixture'], ['title' => 'One-hour package fixture', 'duration_minutes' => 60, 'price' => '25.00', 'currency' => 'USD', 'active' => true, 'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
    }
}
