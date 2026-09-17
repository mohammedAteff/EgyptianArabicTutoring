<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\Media;
use App\Domains\CMS\Models\Page;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceCoverSharedMediaTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected ResourceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin@egyptianarabic.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'admin',
        ]);

        $this->category = ResourceCategory::create([
            'name' => 'Vocabulary',
            'slug' => 'vocabulary',
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    public function test_replacing_resource_cover_preserves_media_referenced_by_another_resource(): void
    {
        Storage::fake('public');

        // Create a shared cover image on the public disk
        $sharedCoverPath = 'media/shared_cover.jpg';
        Storage::disk('public')->put($sharedCoverPath, 'SHARED-COVER-BYTES');

        // Resource 1 uses the cover
        $resource1 = Resource::create([
            'title' => 'Resource One',
            'slug' => 'resource-one',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'cover_image_path' => $sharedCoverPath,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Resource 2 also uses the cover
        $resource2 = Resource::create([
            'title' => 'Resource Two',
            'slug' => 'resource-two',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'cover_image_path' => $sharedCoverPath,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Upload new cover for Resource 1
        $newCover = UploadedFile::fake()->image('new_cover1.png');

        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.resources.update', $resource1), [
                'title' => 'Resource One Updated',
                'slug' => 'resource-one',
                'category_id' => $this->category->id,
                'file_type' => 'pdf',
                'cover_file' => $newCover,
                'status' => 'published',
            ]);

        $response->assertRedirect(route('admin.resources.index'));

        // Resource 1's cover was updated
        $resource1->refresh();
        $this->assertNotEquals($sharedCoverPath, $resource1->cover_image_path);

        // Crucial: The shared cover was NOT deleted because Resource 2 still uses it!
        Storage::disk('public')->assertExists($sharedCoverPath);
    }

    public function test_replacing_resource_cover_preserves_media_referenced_by_page_or_game_or_setting(): void
    {
        Storage::fake('public');

        $pageCoverPath = 'media/page_og_cover.jpg';
        Storage::disk('public')->put($pageCoverPath, 'PAGE-COVER-BYTES');

        // Referenced on a Page
        Page::create([
            'title' => 'Curriculum Page',
            'slug' => 'curriculum',
            'content' => 'Curriculum details',
            'og_image_path' => $pageCoverPath,
            'status' => 'published',
        ]);

        // Resource uses that same media path as its cover
        $resource = Resource::create([
            'title' => 'Resource Three',
            'slug' => 'resource-three',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'cover_image_path' => $pageCoverPath,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Replace Resource Three's cover
        $newCover = UploadedFile::fake()->image('new_cover3.png');
        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.resources.update', $resource), [
                'title' => 'Resource Three Updated',
                'slug' => 'resource-three',
                'category_id' => $this->category->id,
                'file_type' => 'pdf',
                'cover_file' => $newCover,
                'status' => 'published',
            ]);

        $response->assertRedirect(route('admin.resources.index'));

        // The media file was NOT deleted because Curriculum Page still references it
        Storage::disk('public')->assertExists($pageCoverPath);
    }

    public function test_replacing_unreferenced_resource_cover_safely_retires_old_file(): void
    {
        Storage::fake('public');

        $unreferencedCoverPath = 'media/unreferenced_cover.jpg';
        Storage::disk('public')->put($unreferencedCoverPath, 'ORPHAN-COVER-BYTES');

        $resource = Resource::create([
            'title' => 'Solo Resource',
            'slug' => 'solo-resource',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'cover_image_path' => $unreferencedCoverPath,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Replace Solo Resource's cover
        $newCover = UploadedFile::fake()->image('new_solo_cover.png');
        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.resources.update', $resource), [
                'title' => 'Solo Resource Updated',
                'slug' => 'solo-resource',
                'category_id' => $this->category->id,
                'file_type' => 'pdf',
                'cover_file' => $newCover,
                'status' => 'published',
            ]);

        $response->assertRedirect(route('admin.resources.index'));

        // Since it was completely unreferenced elsewhere, the old file was safely deleted
        Storage::disk('public')->assertMissing($unreferencedCoverPath);
    }
}
