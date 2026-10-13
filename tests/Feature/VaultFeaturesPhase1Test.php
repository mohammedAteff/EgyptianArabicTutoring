<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Financial\Models\ReasonCode;
use App\Domains\Reporting\Services\BusinessLifecycleReport;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceCollection;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class VaultFeaturesPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Administrator::factory()->create([
            'role' => 'super_admin',
        ]);
    }

    public function test_reason_codes_table_and_model_functionality(): void
    {
        $reason = ReasonCode::create([
            'category' => 'refund',
            'code' => 'test_reason',
            'label' => 'Test Refund Reason',
            'active' => true,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('reason_codes', [
            'category' => 'refund',
            'code' => 'test_reason',
        ]);
        $this->assertEquals('Test Refund Reason', $reason->label);
    }

    public function test_resource_collections_tables_and_relationships(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Grammar',
            'slug' => 'grammar',
            'active' => true,
            'sort_order' => 1,
        ]);
        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Sample Resource',
            'slug' => 'sample-resource',
            'status' => 'published',
        ]);

        $collection = ResourceCollection::create([
            'title' => 'Beginner Grammar Mastery',
            'slug' => 'beginner-grammar-mastery',
            'description' => 'Curated collection of Egyptian grammar exercises',
            'active' => true,
        ]);

        $collection->resources()->attach($resource->id, ['sort_order' => 1]);

        $this->assertCount(1, $collection->fresh()->resources);
        $this->assertCount(1, $resource->fresh()->collections);
    }

    public function test_unified_student_timeline_service(): void
    {
        $student = Student::factory()->verified()->create();
        $service = app(StudentTimelineService::class);
        $timeline = $service->timeline($this->admin, $student);

        $this->assertInstanceOf(Collection::class, $timeline);
    }

    public function test_admin_can_preview_student_portal_read_only(): void
    {
        $student = Student::factory()->verified()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.students.preview-portal', $student));

        $response->assertOk()
            ->assertSee('Read-Only Student Portal Preview')
            ->assertSee('Exit Preview');
    }

    public function test_assistant_cannot_preview_student_portal(): void
    {
        $assistant = Administrator::factory()->create([
            'role' => 'assistant',
        ]);
        $student = Student::factory()->verified()->create();

        $response = $this->actingAs($assistant, 'web')
            ->get(route('admin.students.preview-portal', $student));

        $response->assertForbidden();
    }

    public function test_business_lifecycle_report_includes_cancellation_breakdown(): void
    {
        $report = app(BusinessLifecycleReport::class);
        $filters = $report->filters(new Request([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $totals = $report->totals($filters);

        $this->assertArrayHasKey('cancellations', $totals);
        $this->assertArrayHasKey('total_cancellations', $totals['cancellations']);
        $this->assertArrayHasKey('restored', $totals['cancellations']);
        $this->assertArrayHasKey('retained', $totals['cancellations']);
    }
}
