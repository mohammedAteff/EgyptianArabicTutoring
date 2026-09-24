<?php

namespace Tests\Unit;

use App\Domains\Database\Services\DatabaseCapability;
use PHPUnit\Framework\TestCase;

class DatabaseCapabilityTest extends TestCase
{
    public function test_mariadb_11_2_or_later_uses_copy_for_online_foreign_key_additions(): void
    {
        $this->assertSame('COPY', DatabaseCapability::onlineForeignKeyAddAlgorithm('mariadb', '11.2.0'));
        $this->assertSame('COPY', DatabaseCapability::onlineForeignKeyAddAlgorithm('MariaDB', '11.8.9'));
        $this->assertSame('INPLACE', DatabaseCapability::onlineForeignKeyAddAlgorithm('mariadb', '11.1.9'));
        $this->assertSame('INPLACE', DatabaseCapability::onlineForeignKeyAddAlgorithm('mysql', '8.4.0'));
    }
}
