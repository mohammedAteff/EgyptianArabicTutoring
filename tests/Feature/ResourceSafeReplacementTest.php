<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceSafeReplacementTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected ResourceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Resource Admin',
            'email' => 'resource.admin@example.com',
            'password' => Hash::make('AdminPassword123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->category = ResourceCategory::create([
            'name' => 'Grammar Workbooks',
            'slug' => 'grammar-workbooks',
            'active' => true,
        ]);
    }

    public function test_successful_resource_file_replacement_switches_file_and_retires_old_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Prepare existing file
        $oldPath = 'resources/original-version.pdf';
        Storage::disk('local')->put($oldPath, 'OLD_VERSION_CONTENT');

        $resource = Resource::create([
            'title' => 'Original Grammar Guide',
            'slug' => 'original-grammar-guide',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'file_path' => $oldPath,
            'file_size' => 1024,
            'status' => 'published',
            'is_gated' => true,
        ]);

        $replacementFile = UploadedFile::fake()->create('updated-guide.pdf', 2048, 'application/pdf');

        $response = $this->actingAs($this->admin, 'web')->put(route('admin.resources.update', $resource->id), [
            'title' => 'Updated Grammar Guide',
            'slug' => 'original-grammar-guide',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'published',
            'file' => $replacementFile,
        ]);

        $response->assertRedirect(route('admin.resources.index'));
        $resource->refresh();

        $this->assertNotEquals($oldPath, $resource->file_path);
        $this->assertFalse(Storage::disk('local')->exists($oldPath), 'Old file should be retired');
        $this->assertTrue(Storage::disk('local')->exists($resource->file_path), 'New file must exist on disk');
    }

    public function test_shared_file_referenced_by_another_resource_is_not_deleted_when_first_resource_is_replaced(): void
    {
        Storage::fake('local');

        $sharedPath = 'resources/shared-common-materials.pdf';
        Storage::disk('local')->put($sharedPath, 'SHARED_MATERIALS_BYTES');

        $resourceA = Resource::create([
            'title' => 'Resource A',
            'slug' => 'resource-a',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'file_path' => $sharedPath,
            'file_size' => 512,
            'status' => 'published',
            'is_gated' => true,
        ]);

        $resourceB = Resource::create([
            'title' => 'Resource B',
            'slug' => 'resource-b',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'file_path' => $sharedPath,
            'file_size' => 512,
            'status' => 'published',
            'is_gated' => true,
        ]);

        $replacementFile = UploadedFile::fake()->create('new-a-only.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->admin, 'web')->put(route('admin.resources.update', $resourceA->id), [
            'title' => 'Resource A (V2)',
            'slug' => 'resource-a',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'published',
            'file' => $replacementFile,
        ]);

        $response->assertRedirect(route('admin.resources.index'));
        $resourceA->refresh();
        $resourceB->refresh();

        $this->assertNotEquals($sharedPath, $resourceA->file_path);
        $this->assertEquals($sharedPath, $resourceB->file_path);
        $this->assertTrue(Storage::disk('local')->exists($sharedPath), 'Shared file must NOT be deleted because resource B still references it');
        $this->assertTrue(Storage::disk('local')->exists($resourceA->file_path));
    }

    public function test_failed_database_save_leaves_old_file_intact_and_cleans_up_new_temp_file(): void
    {
        Storage::fake('local');

        $oldPath = 'resources/critical-existing-file.pdf';
        Storage::disk('local')->put($oldPath, 'PRECIOUS_EXISTING_DATA');

        $resource = Resource::create([
            'title' => 'Critical Resource',
            'slug' => 'critical-resource',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'file_path' => $oldPath,
            'file_size' => 2048,
            'status' => 'published',
            'is_gated' => true,
        ]);

        // Intercept saving event to simulate database failure
        Resource::saving(function ($model) {
            if ($model->title === 'Failing Title Trigger') {
                throw new \RuntimeException('Database constraint error simulated');
            }
        });

        $replacementFile = UploadedFile::fake()->create('replacement.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->admin, 'web')->put(route('admin.resources.update', $resource->id), [
            'title' => 'Failing Title Trigger',
            'slug' => 'critical-resource',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'published',
            'file' => $replacementFile,
        ]);

        $response->assertSessionHas('error');
        $resource->refresh();

        // Old file must still exist and database record still points to it
        $this->assertEquals($oldPath, $resource->file_path);
        $this->assertTrue(Storage::disk('local')->exists($oldPath), 'Old file must remain intact after DB failure');
        $this->assertEquals('PRECIOUS_EXISTING_DATA', Storage::disk('local')->get($oldPath));

        // Ensure no orphaned files remain in resources/ other than the oldPath
        $allFiles = Storage::disk('local')->files('resources');
        $this->assertEquals([$oldPath], $allFiles, 'Any newly uploaded file should have been removed on failure');
    }

    public function test_non_throwing_failed_storage_upload_leaves_old_file_intact_and_returns_error(): void
    {
        Storage::fake('local');

        $oldPath = 'resources/original-safe.pdf';
        Storage::disk('local')->put($oldPath, 'OLD_SAFE_DATA');

        $resource = Resource::create([
            'title' => 'Safe Resource',
            'slug' => 'safe-resource',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'file_path' => $oldPath,
            'file_size' => 1024,
            'status' => 'published',
            'is_gated' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_pdf');
        file_put_contents($tempFile, '%PDF-1.4 test content');

        $failingFile = new class($tempFile, 'attempt.pdf', 'application/pdf', null, true) extends UploadedFile
        {
            public function storeAs($path, $name = null, $options = []): string|false
            {
                return false;
            }
        };

        $response = $this->actingAs($this->admin, 'web')->put(route('admin.resources.update', $resource->id), [
            'title' => 'Safe Resource',
            'slug' => 'safe-resource',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'published',
            'file' => $failingFile,
        ]);

        $response->assertSessionHas('error');
        $resource->refresh();

        $this->assertEquals($oldPath, $resource->file_path);
        $this->assertTrue(Storage::disk('local')->exists($oldPath));
        $this->assertEquals('OLD_SAFE_DATA', Storage::disk('local')->get($oldPath));

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_throwing_storage_upload_leaves_old_file_intact_and_returns_error(): void
    {
        Storage::fake('local');

        $oldPath = 'resources/original-safe-throwing.pdf';
        Storage::disk('local')->put($oldPath, 'OLD_SAFE_THROWING_DATA');

        $resource = Resource::create([
            'title' => 'Safe Resource Throwing',
            'slug' => 'safe-resource-throwing',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'file_path' => $oldPath,
            'file_size' => 1024,
            'status' => 'published',
            'is_gated' => true,
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_pdf');
        file_put_contents($tempFile, '%PDF-1.4 test content');

        $throwingFile = new class($tempFile, 'throwing.pdf', 'application/pdf', null, true) extends UploadedFile
        {
            public function storeAs($path, $name = null, $options = []): string|false
            {
                throw new \RuntimeException('Simulated Disk Write Exception');
            }
        };

        $response = $this->actingAs($this->admin, 'web')->put(route('admin.resources.update', $resource->id), [
            'title' => 'Safe Resource Throwing',
            'slug' => 'safe-resource-throwing',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'published',
            'file' => $throwingFile,
        ]);

        $response->assertSessionHas('error');
        $resource->refresh();

        $this->assertEquals($oldPath, $resource->file_path);
        $this->assertTrue(Storage::disk('local')->exists($oldPath));
        $this->assertEquals('OLD_SAFE_THROWING_DATA', Storage::disk('local')->get($oldPath));

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }
}
