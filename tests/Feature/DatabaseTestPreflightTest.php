<?php

namespace Tests\Feature;

use App\Domains\Database\Services\DatabaseCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

trait TestPreflightSentinel
{
    protected function setUpTestPreflightSentinel(): void
    {
        $this->preflightSentinelRan = true;
    }
}

class DatabaseTestPreflightTest extends TestCase
{
    use RefreshDatabase;
    use TestPreflightSentinel;

    protected bool $preflightSentinelRan = false;

    public function test_database_vendor_gate_runs_before_database_refresh_traits(): void
    {
        $capability = Mockery::mock(DatabaseCapability::class);
        $capability->shouldReceive('assertMatchesReconciledEnvironment')
            ->once()
            ->andThrow(new RuntimeException('preflight mismatch'));
        $this->app->instance(DatabaseCapability::class, $capability);
        $this->preflightSentinelRan = false;

        try {
            $this->setUpTraits();
        } catch (RuntimeException $exception) {
            $this->assertSame('preflight mismatch', $exception->getMessage());
            $this->assertFalse($this->preflightSentinelRan);

            return;
        }

        $this->fail('A database vendor mismatch must stop test setup before any test traits run.');
    }
}
