<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHealthAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $superAdmin;

    protected Administrator $ordinaryAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Administrator::create([
            'name' => 'Super Tutor',
            'email' => 'super@boltlanding.test',
            'password' => Hash::make('Password123!'),
            'role' => 'super_admin',
        ]);

        $this->ordinaryAdmin = Administrator::create([
            'name' => 'Staff Assistant',
            'email' => 'staff@boltlanding.test',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
        ]);
    }

    public function test_health_endpoint_authorization_matrix(): void
    {
        // 1. Unauthenticated guest is redirected to admin login
        $guestResponse = $this->get(route('admin.health'));
        $guestResponse->assertRedirect(route('admin.login'));

        // 2. Ordinary admin receives 403 Forbidden
        $adminResponse = $this->actingAs($this->ordinaryAdmin, 'web')->get(route('admin.health'));
        $adminResponse->assertStatus(403);

        // 3. Super admin succeeds with 200 OK and health view
        $superResponse = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $superResponse->assertStatus(200);
        $superResponse->assertViewIs('admin.system.health');
        $superResponse->assertViewHas([
            'dbStatus',
            'storageStatus',
            'cacheStatus',
            'backupStatus',
            'schedulerStatus',
            'queueStatus',
            'mailStatus',
        ]);
    }

    public function test_scheduler_heartbeat_execution_updates_setting(): void
    {
        Setting::query()->where('key', 'last_scheduler_run_at')->delete();
        $this->assertNull(Setting::get('last_scheduler_run_at'));

        // Run schedule:run
        $this->artisan('schedule:run')->assertExitCode(0);

        $lastHeartbeat = Setting::get('last_scheduler_run_at');
        $this->assertNotNull($lastHeartbeat);

        $parsed = CarbonImmutable::parse($lastHeartbeat);
        $this->assertTrue($parsed->diffInSeconds(now('UTC')) < 30);
    }

    public function test_scheduled_commands_run_in_process_without_proc_open(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->keyBy(fn ($event) => $event->getSummaryForDisplay());

        $commands = [
            'booking:cleanup-holds' => ['booking:cleanup-holds', [], 'last_holds_cleanup_at'],
            'analytics:aggregate-daily --prune' => ['analytics:aggregate-daily', ['--prune' => true], 'last_analytics_aggregation_at'],
            'backup:run --clean' => ['backup:run', ['--clean' => true], null],
        ];

        foreach ($commands as $name => [$command, $arguments, $successSetting]) {
            $event = $events->get($name);
            $this->assertInstanceOf(CallbackEvent::class, $event);

            Artisan::shouldReceive('call')->once()->with($command, $arguments)->andReturn(0);
            $event->run(app());

            $this->assertSame(0, $event->exitCode);
            if ($successSetting !== null) {
                $this->assertNotNull(Setting::get($successSetting));
            }
        }
    }

    public function test_scheduler_health_reporting_statuses(): void
    {
        // Case A: No heartbeat recorded -> Warning
        Setting::query()->where('key', 'last_scheduler_run_at')->delete();
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('warning', $response->viewData('schedulerStatus'));
        $this->assertEquals('No heartbeat recorded', $response->viewData('schedulerMessage'));

        // Case B: Fresh heartbeat (2 minutes ago) -> Healthy
        Setting::set('last_scheduler_run_at', now('UTC')->subMinutes(2)->toIso8601String(), 'system');
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('healthy', $response->viewData('schedulerStatus'));
        $this->assertEquals('Scheduler active', $response->viewData('schedulerMessage'));

        // Case C: Stale heartbeat (15 minutes ago) -> Unhealthy
        Setting::set('last_scheduler_run_at', now('UTC')->subMinutes(15)->toIso8601String(), 'system');
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('unhealthy', $response->viewData('schedulerStatus'));
        $this->assertEquals('Scheduler stale', $response->viewData('schedulerMessage'));
    }

    public function test_queue_failed_jobs_reporting(): void
    {
        DB::table('failed_jobs')->delete();

        // Case A: 0 failed jobs -> Healthy
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('healthy', $response->viewData('queueStatus'));
        $this->assertEquals(0, $response->viewData('failedJobsCount'));

        // Case B: Has failed jobs -> Warning
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test Queue Exception',
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('warning', $response->viewData('queueStatus'));
        $this->assertEquals(1, $response->viewData('failedJobsCount'));
        $this->assertStringContainsString('1 failed job in queue', $response->viewData('queueMessage'));
    }

    public function test_backup_freshness_reporting(): void
    {
        config(['filesystems.backup_disk' => 's3']);

        // Case A: never_run -> Warning
        Setting::set('last_backup_status', 'never_run', 'system');
        Setting::query()->where('key', 'last_backup_at')->delete();
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('warning', $response->viewData('backupStatus'));
        $this->assertEquals('No backup has been executed yet', $response->viewData('backupMessage'));

        // Case B: failed -> Unhealthy
        Setting::set('last_backup_status', 'failed: disk out of space', 'system');
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('unhealthy', $response->viewData('backupStatus'));
        $this->assertEquals('failed: disk out of space', $response->viewData('backupMessage'));

        // Case C: Success but older than 36 hours -> Warning
        Setting::set('last_backup_status', 'success', 'system');
        Setting::set('last_backup_at', now('UTC')->subHours(40)->toIso8601String(), 'system');
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('warning', $response->viewData('backupStatus'));
        $this->assertEquals('Last backup is over 36 hours old', $response->viewData('backupMessage'));

        // Case D: Recent success (2 hours ago) -> Healthy
        Setting::set('last_backup_status', 'success', 'system');
        Setting::set('last_backup_at', now('UTC')->subHours(2)->toIso8601String(), 'system');
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('healthy', $response->viewData('backupStatus'));
        $this->assertStringContainsString('Last snapshot:', $response->viewData('backupMessage'));
    }

    public function test_mail_configuration_reporting(): void
    {
        // Case A: SMTP driver
        config(['mail.default' => 'smtp', 'mail.from.address' => 'tutor@example.com']);
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('healthy', $response->viewData('mailStatus'));
        $this->assertEquals('smtp', $response->viewData('mailDriver'));
        $this->assertEquals('tutor@example.com', $response->viewData('mailFrom'));

        // Case B: Log driver in non-production environment
        config(['mail.default' => 'log']);
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);
        $this->assertEquals('healthy', $response->viewData('mailStatus'));
        $this->assertStringContainsString('development mode', $response->viewData('mailMessage'));
    }

    public function test_core_system_diagnostics(): void
    {
        $response = $this->actingAs($this->superAdmin, 'web')->get(route('admin.health'));
        $response->assertStatus(200);

        // Database check passes
        $this->assertEquals('healthy', $response->viewData('dbStatus'));
        $this->assertNotEmpty($response->viewData('dbDetails'));

        // Storage check passes
        $this->assertEquals('healthy', $response->viewData('storageStatus'));

        // Cache check passes
        $this->assertEquals('healthy', $response->viewData('cacheStatus'));

        // PHP and Laravel versions are returned
        $this->assertEquals(PHP_VERSION, $response->viewData('phpVersion'));
        $this->assertEquals(app()->version(), $response->viewData('laravelVersion'));
    }

    public function test_queue_worker_heartbeat_listeners_update_cache_on_real_queue_events(): void
    {
        Cache::forget('queue_worker_heartbeat_at');
        $this->assertNull(Cache::get('queue_worker_heartbeat_at'));

        // Fire real Queue::looping event
        event(new Looping('database', 'default'));

        $heartbeat = Cache::get('queue_worker_heartbeat_at');
        $this->assertNotNull($heartbeat, 'Queue::looping event must record queue_worker_heartbeat_at in cache without crashing');

        $parsed = CarbonImmutable::parse($heartbeat);
        $this->assertTrue($parsed->diffInSeconds(now('UTC')) < 5);

        // Advance time and fire Queue::before event
        CarbonImmutable::setTestNow(now('UTC')->addSeconds(10));
        $jobMock = \Mockery::mock(Job::class);
        $jobMock->shouldReceive('payload')->andReturn([]);
        event(new JobProcessing('database', $jobMock));

        $updatedHeartbeat = Cache::get('queue_worker_heartbeat_at');
        $this->assertNotNull($updatedHeartbeat);
        $this->assertNotEquals($heartbeat, $updatedHeartbeat);

        CarbonImmutable::setTestNow();
    }
}
