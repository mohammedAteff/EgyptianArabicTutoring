<?php

namespace App\Domains\Database\Services;

use App\Domains\Database\Exceptions\UnsupportedDatabaseVendorException;
use Closure;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class DatabaseCapability
{
    private string $driver;

    private string $vendor;

    private string $rawVersion;

    private string $version;

    public function __construct(bool $deferReconciledCheck = false)
    {
        $connection = DB::connection();
        $this->driver = $connection->getDriverName();
        $pdo = $connection->getPdo();
        $this->rawVersion = trim((string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

        if ($this->driver === 'pgsql') {
            $this->vendor = 'pgsql';
            $this->version = $this->extractVersion($this->rawVersion);
        } elseif (in_array($this->driver, ['mysql', 'mariadb'], true)) {
            $isMariaDb = $this->driver === 'mariadb' || stripos($this->rawVersion, 'MariaDB') !== false;
            $this->vendor = $isMariaDb ? 'mariadb' : 'mysql';
            $versionText = $isMariaDb ? (string) preg_replace('/^5\.5\.5-/', '', $this->rawVersion) : $this->rawVersion;
            $this->version = $this->extractVersion($versionText);
        } else {
            throw new UnsupportedDatabaseVendorException("Unsupported database driver [{$this->driver}]. Use a reconciled MySQL, MariaDB, or PostgreSQL release.");
        }

        if ($this->version === '') {
            throw new UnsupportedDatabaseVendorException("Could not identify a supported server release from [{$this->rawVersion}].");
        }

        if (app()->isProduction() && ! $deferReconciledCheck) {
            $this->assertMatchesReconciledEnvironment(
                config('database.reconciled.vendor'),
                config('database.reconciled.version'),
            );
        }
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function vendor(): string
    {
        return $this->vendor;
    }

    public function rawVersion(): string
    {
        return $this->rawVersion;
    }

    public function version(): string
    {
        return $this->version;
    }

    public static function onlineForeignKeyAddAlgorithm(string $vendor, string $version): string
    {
        return strtolower(trim($vendor)) === 'mariadb' && version_compare($version, '11.2.0', '>=')
            ? 'COPY'
            : 'INPLACE';
    }

    public function assertMatchesReconciledEnvironment(?string $expectedVendor, ?string $expectedVersion = null): void
    {
        $expectedVendor = strtolower(trim((string) $expectedVendor));
        $expectedVersion = trim((string) $expectedVersion);
        if ($expectedVendor === '' || $expectedVersion === '') {
            throw new RuntimeException('Database verification gate failed: set DB_RECONCILED_VENDOR and the exact DB_RECONCILED_VERSION after verifying the target production server.');
        }

        if ($this->driver === 'sqlite' || $this->vendor !== $expectedVendor || $this->version !== $this->extractVersion($expectedVersion)) {
            throw new RuntimeException("Database verification gate failed: expected {$expectedVendor} {$expectedVersion}; detected driver {$this->driver}, vendor {$this->vendor}, version {$this->version}. Concurrency sign-off on SQLite is prohibited.");
        }
    }

    /** @template T of mixed
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transaction(Closure $callback, int $attempts = 3): mixed
    {
        if (app()->isProduction()) {
            $this->assertMatchesReconciledEnvironment(
                config('database.reconciled.vendor'),
                config('database.reconciled.version'),
            );
        }

        if ($attempts < 1) {
            throw new RuntimeException('A transaction must allow at least one attempt.');
        }

        if (DB::transactionLevel() > 0) {
            return DB::transaction($callback, $attempts);
        }

        $previousIsolation = $this->prepareTransactionIsolation();
        try {
            return DB::transaction(function () use ($callback): mixed {
                $this->applyTransactionIsolationInsideTransaction();

                return $callback();
            }, $attempts);
        } finally {
            $this->restoreTransactionIsolation($previousIsolation);
        }
    }

    /** Connection-scoped setup; call only before beginning the transaction. */
    public function prepareTransactionIsolation(): ?string
    {
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Session isolation must be prepared before DB::transaction().');
        }

        if ($this->vendor === 'pgsql') {
            return null;
        }

        $variable = match ($this->vendor) {
            'mysql' => version_compare($this->version, '8.0.3', '>=') ? 'transaction_isolation' : 'tx_isolation',
            'mariadb' => version_compare($this->version, '11.1.1', '>=') ? 'transaction_isolation' : 'tx_isolation',
            default => throw new UnsupportedDatabaseVendorException("No isolation setup is verified for {$this->vendor}.")
        };
        $previous = DB::scalar("SELECT @@SESSION.{$variable}");
        if (! is_string($previous) || trim($previous) === '') {
            throw new UnsupportedDatabaseVendorException("Could not read the {$this->vendor} session isolation level.");
        }

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');

        return $previous;
    }

    /** Transaction-scoped setup; PostgreSQL requires this after BEGIN. */
    public function applyTransactionIsolationInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Transaction isolation can only be applied inside an active transaction.');
        }

        if ($this->vendor === 'pgsql') {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
    }

    /** Restore a MySQL-family connection's previous session value after commit or rollback. */
    public function restoreTransactionIsolation(?string $previous): void
    {
        if ($previous === null || $this->vendor === 'pgsql') {
            return;
        }

        $normalized = strtoupper(str_replace('-', ' ', trim($previous)));
        if (! in_array($normalized, ['READ COMMITTED', 'READ UNCOMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'], true)) {
            throw new UnsupportedDatabaseVendorException("Refusing to restore an unrecognized {$this->vendor} isolation level.");
        }

        DB::statement("SET SESSION TRANSACTION ISOLATION LEVEL {$normalized}");
    }

    private function extractVersion(string $version): string
    {
        return preg_match('/(\d+\.\d+(?:\.\d+)?)/', $version, $matches) === 1 ? $matches[1] : '';
    }
}
