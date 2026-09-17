<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeedingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_overwrite_existing_administrator_password(): void
    {
        // 1. Create administrator with known password hash
        $customHash = Hash::make('CustomOriginalPassword123!');
        $admin = Administrator::create([
            'name' => 'Original Admin',
            'email' => 'admin@egyptianarabic.test',
            'password' => $customHash,
            'role' => 'super_admin',
        ]);

        // 2. Run DatabaseSeeder
        $this->seed(DatabaseSeeder::class);

        // 3. Assert administrator was NOT overwritten or modified
        $refreshedAdmin = Administrator::where('email', 'admin@egyptianarabic.test')->first();
        $this->assertNotNull($refreshedAdmin);
        $this->assertSame('Original Admin', $refreshedAdmin->name);
        $this->assertSame($customHash, $refreshedAdmin->password);
        $this->assertTrue(Hash::check('CustomOriginalPassword123!', $refreshedAdmin->password));
        $this->assertFalse(Hash::check('password', $refreshedAdmin->password));
    }

    public function test_database_seeder_does_not_create_duplicate_admins_if_any_admin_exists(): void
    {
        Administrator::create([
            'name' => 'Existing Host Admin',
            'email' => 'host@egyptianarabic.test',
            'password' => Hash::make('HostPassword123!'),
            'role' => 'super_admin',
        ]);

        $this->assertSame(1, Administrator::count());

        $this->seed(DatabaseSeeder::class);

        // Count should remain 1 (no default admin injected)
        $this->assertSame(1, Administrator::count());
        $this->assertNull(Administrator::where('email', 'admin@egyptianarabic.test')->first());
    }
}
