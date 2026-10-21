<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\System\Services\BackupService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTriggerIntegrityTest extends TestCase
{
    use DatabaseTruncation;

    public function test_native_archive_preserves_verified_trigger_definitions(): void
    {
        Storage::fake('managed_backups');
        Storage::fake('local');
        Storage::fake('public');
        config(['filesystems.backup_disk' => null]);
        $this->mock(TelegramAutomationService::class)->shouldReceive('emit')->once()->withArgs(fn ($trigger) => $trigger === 'backup_success');
        $service = app(BackupService::class);
        $path = $service->createBackup();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();
        $objects = $service->validatedDatabaseObjects($path, $manifest);
        $this->assertSame($service->snapshotDatabaseObjects(), $objects);
        $this->assertContains('ledger_typed_insert_guard', array_column($objects, 'name'));
        $this->assertContains('booking_typed_update_guard', array_column($objects, 'name'));
    }

    public function test_recreated_trigger_still_rejects_invalid_booking_provenance(): void
    {
        Storage::fake('managed_backups');
        $service = app(BackupService::class);
        $objects = $service->snapshotDatabaseObjects();
        $booking = Booking::factory()->create();
        foreach ($objects as $object) {
            DB::unprepared('DROP TRIGGER `'.$object['name'].'`');
        }
        $service->restoreDatabaseObjects($objects);
        try {
            DB::table('bookings')->where('id', $booking->id)->update(['funding_mode' => 'package']);
            $this->fail('Restored integrity guard accepted an incomplete allocation.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Incomplete booking entitlement snapshot', $exception->getMessage());
        }
    }

    public function test_missing_or_changed_object_manifest_is_rejected_before_restoration(): void
    {
        Storage::fake('managed_backups');
        $path = Storage::disk('managed_backups')->path('tampered.zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('database-objects.json', '[]');
        $zip->close();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Archive lacks verified database objects');
        app(BackupService::class)->validatedDatabaseObjects($path, ['database_objects_version' => 1, 'database_objects_sha256' => str_repeat('0', 64)]);
    }
}
