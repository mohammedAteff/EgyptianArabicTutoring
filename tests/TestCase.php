<?php

namespace Tests;

use App\Domains\Database\Services\DatabaseCapability;
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
}
