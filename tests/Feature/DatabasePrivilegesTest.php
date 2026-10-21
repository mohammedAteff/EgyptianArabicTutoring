<?php

namespace Tests\Feature;

use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DatabasePrivilegesTest extends TestCase
{
    use DatabaseTruncation;

    public function test_runtime_account_preview_restricts_financial_history_without_mutating_grants(): void
    {
        $this->artisan('database:runtime-access', ['username' => 'arabic_runtime_preview', '--no-interaction' => true])
            ->expectsOutputToContain('GRANT SELECT, INSERT ON `bolt_landing_test`.`audit_logs`')
            ->expectsOutputToContain('GRANT SELECT, INSERT ON `bolt_landing_test`.`payment_records`')
            ->expectsOutputToContain('Preview only. No accounts, passwords or grants were changed.')
            ->assertSuccessful();
    }

    public function test_only_privileged_staff_requests_use_the_separate_account_and_connection_is_restored(): void
    {
        config(['database.connections.maintenance' => config('database.connections.mysql')]);
        Route::middleware('web')->get('/admin/database-context-fixture', fn () => response(DB::getDefaultConnection()))->name('admin.database-context-fixture');
        foreach (['assistant' => 'mysql', 'admin' => 'maintenance', 'super_admin' => 'maintenance'] as $role => $connection) {
            $actor = AdministratorFactory::new()->create(['role' => $role]);
            $this->actingAs($actor, 'web')->get('/admin/database-context-fixture')->assertOk()->assertSee($connection);
            $this->assertSame('mysql', DB::getDefaultConnection());
        }
        Route::middleware('web')->get('/public/database-context-fixture', fn () => response(DB::getDefaultConnection()));
        $this->get('/public/database-context-fixture')->assertOk()->assertSee('mysql');
    }

    public function test_maintenance_context_refuses_a_different_database(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        config(['database.connections.maintenance' => array_replace(config('database.connections.mysql'), ['database' => 'forbidden_other_database'])]);
        Route::middleware('web')->get('/admin/database-mismatch-fixture', fn () => response('unexpected'))->name('admin.database-mismatch-fixture');
        $this->actingAs($actor, 'web')->get('/admin/database-mismatch-fixture')->assertStatus(503);
        $this->assertSame('mysql', DB::getDefaultConnection());
    }

    public function test_global_accounts_and_unattended_privilege_changes_are_refused(): void
    {
        $this->artisan('database:runtime-access', ['username' => 'root', '--no-interaction' => true])->assertFailed();
        $this->artisan('database:runtime-access', ['username' => 'arabic_runtime_preview', '--apply' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Grant changes require interactive confirmation. No grants were changed.')->assertFailed();
    }
}
