<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExternalResourceLinkingTest extends TestCase
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

    public function test_admin_can_create_resource_with_valid_external_url(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.resources.store'), [
                'title' => 'External Grammar Guide',
                'slug' => 'external-grammar-guide',
                'category_id' => $this->category->id,
                'file_type' => 'pdf',
                'external_url' => 'https://example.com/materials/guide.pdf',
                'status' => 'published',
                'is_gated' => 0,
            ]);

        $response->assertRedirect(route('admin.resources.index'));

        $this->assertDatabaseHas('resources', [
            'slug' => 'external-grammar-guide',
            'external_url' => 'https://example.com/materials/guide.pdf',
        ]);
    }

    public function test_external_url_scheme_validation_rejects_unapproved_schemes(): void
    {
        $invalidUrls = [
            'javascript:alert(1)',
            'data:text/html;base64,PHNjcmlwdD4=',
            'file:///etc/passwd',
            '//malicious.com/payload.pdf',
            'ftp://example.com/file.pdf',
        ];

        foreach ($invalidUrls as $invalidUrl) {
            $response = $this->actingAs($this->admin, 'web')
                ->post(route('admin.resources.store'), [
                    'title' => 'Invalid Resource',
                    'slug' => 'invalid-resource-'.rand(100, 999),
                    'category_id' => $this->category->id,
                    'file_type' => 'pdf',
                    'external_url' => $invalidUrl,
                    'status' => 'draft',
                    'is_gated' => 0,
                ]);

            $response->assertSessionHasErrors('external_url');
        }
    }

    public function test_downloading_resource_with_external_url_redirects_away_and_records_download(): void
    {
        $resource = Resource::create([
            'title' => 'External Sheet',
            'slug' => 'external-sheet',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'external_url' => 'https://cdn.example.org/arabic-verbs.pdf',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'is_gated' => false,
        ]);

        $response = $this->get(route('resources.download', ['slug' => $resource->slug]));

        $response->assertRedirect('https://cdn.example.org/arabic-verbs.pdf');

        $this->assertDatabaseHas('resource_downloads', [
            'resource_id' => $resource->id,
        ]);
    }
}
