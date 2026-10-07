<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseStudioAssetSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Administrator,Course,string} */
    private function draft(?Student $owner = null): array
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $studio = app(CourseStudioService::class);
        $course = $studio->create($actor, ['title' => 'Course attachments', 'slug' => 'attachments', 'kind' => $owner ? 'private' : 'catalog', 'owner_student_id' => $owner?->id]);
        if (! $owner) {
            $this->write($actor, $course, 'access', ['audience' => 'public']);
        }
        $this->write($actor, $course, 'add_section', ['title' => 'Files', 'status' => 'published']);
        $section = $studio->draft($actor, $course)['sections'][0];
        $this->write($actor, $course, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'Read and learn', 'slug' => 'read', 'status' => 'published']);
        $lesson = $studio->draft($actor, $course)['sections'][0]['lessons'][0];

        return [$actor, $course->fresh(), $lesson['key']];
    }

    private function write(Administrator $actor, Course $course, string $operation, array $data): Course
    {
        return app(CourseStudioService::class)->write($actor, $course, $operation, $data, $course->fresh()->lock_version);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('lesson.pdf', "%PDF-1.4\nSynthetic course PDF\n%%EOF");
    }

    public function test_image_and_pdf_uploads_use_private_storage_safe_preview_and_no_raw_paths(): void
    {
        Storage::fake('local');
        [$actor,$course,$key] = $this->draft();
        $studio = app(CourseStudioService::class);
        $image = $studio->upload($actor, $course, UploadedFile::fake()->image('image.png', 20, 20), 'image', $course->fresh()->lock_version);
        $file = $studio->upload($actor, $course, $this->pdf(), 'file', $course->fresh()->lock_version);
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'image', 'asset_id' => $image->id, 'alt' => 'صورة الحروف · Alphabet image']);
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'file', 'asset_id' => $file->id, 'label' => 'Download lesson PDF']);
        $this->assertTrue(Storage::disk('local')->exists($image->path));
        $this->assertStringStartsWith('lms-assets/', $image->path);
        $this->assertArrayNotHasKey('path', $image->toArray());
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.preview', $course))->assertOk()->assertSee('Alphabet image')->assertDontSee($image->path, false);
        $this->get(route('admin.lms.courses.assets', [$course, $image]))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $response = $this->get(route('admin.lms.courses.assets', [$course, $file]))->assertDownload('course-attachment.pdf');
        $this->assertSame("%PDF-1.4\nSynthetic course PDF\n%%EOF", file_get_contents($response->baseResponse->getFile()->getPathname()));
        $studio->publish($actor, $course, $course->fresh()->lock_version);
        $this->assertDatabaseCount('lms_assets', 2);
    }

    public function test_svg_html_and_disguised_uploads_are_rejected_without_bytes_or_records(): void
    {
        Storage::fake('local');
        [$actor,$course] = $this->draft();
        $studio = app(CourseStudioService::class);
        foreach ([['image', UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            ['image', UploadedFile::fake()->createWithContent('fake.png', '<html><script>alert(1)</script></html>')],
            ['file', UploadedFile::fake()->createWithContent('bad.pdf', '<html>HTML masquerading as PDF</html>')]] as [$kind,$file]) {
            try {
                $studio->upload($actor, $course, $file, $kind, $course->fresh()->lock_version);
                $this->fail('Unsafe upload must fail.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('lms_assets', 0);
                $this->assertSame([], Storage::disk('local')->allFiles('lms-assets'));
            }
        }
    }

    public function test_cross_course_attachment_and_path_query_tampering_cannot_select_private_bytes(): void
    {
        Storage::fake('local');
        [$actor,$course,$key] = $this->draft();
        $studio = app(CourseStudioService::class);
        $foreign = $studio->create($actor, ['title' => 'Foreign', 'slug' => 'foreign-assets', 'kind' => 'catalog']);
        $asset = $studio->upload($actor, $foreign, UploadedFile::fake()->image('foreign.png', 10, 10), 'image', 1);
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.assets', [$course, $asset]))->assertNotFound();
        $this->postJson(route('admin.lms.courses.update', $course), ['operation' => 'add_block', 'version' => $course->fresh()->lock_version, 'parent_key' => $key, 'kind' => 'image', 'asset_id' => $asset->id, 'alt' => 'Foreign'])->assertUnprocessable()->assertJsonValidationErrors('asset_id');
        $own = $studio->upload($actor, $course, $this->pdf(), 'file', $course->fresh()->lock_version);
        $response = $this->get(route('admin.lms.courses.assets', [$course, $own]).'?path=../../.env&disk=public')->assertDownload();
        $this->assertSame("%PDF-1.4\nSynthetic course PDF\n%%EOF", file_get_contents($response->baseResponse->getFile()->getPathname()));
        DB::table('lms_assets')->where('id', $own->id)->update(['path' => 'lms-assets/../../.env']);
        $this->get(route('admin.lms.courses.assets', [$course, $own]))->assertNotFound();
    }

    public function test_resource_detachment_and_asset_duplication_preserve_shared_original_bytes(): void
    {
        Storage::fake('local');
        [$actor,$course,$key] = $this->draft();
        $studio = app(CourseStudioService::class);
        $resource = ResourceFactory::new()->create(['external_url' => null, 'file_path' => 'resources/shared.pdf']);
        Storage::disk('local')->put('resources/shared.pdf', '%PDF-shared');
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'file', 'source' => 'resource', 'resource_id' => $resource->id, 'label' => 'Shared PDF']);
        $asset = $studio->upload($actor, $course, UploadedFile::fake()->image('shared.png', 10, 10), 'image', $course->fresh()->lock_version);
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'image', 'asset_id' => $asset->id, 'alt' => 'Shared lesson image']);
        $sourceBytes = Storage::disk('local')->get($asset->path);
        $course = $studio->publish($actor, $course, $course->fresh()->lock_version);
        $copy = $studio->duplicate($actor, $course, $course->lock_version);
        $this->assertDatabaseCount('lms_assets', 1);
        $this->assertSame($sourceBytes, Storage::disk('local')->get($asset->path));
        $this->assertSame($asset->id, $studio->draft($actor, $copy)['sections'][0]['lessons'][0]['blocks'][1]['asset_id']);
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.assets', [$copy, $asset]))->assertOk();
        $this->assertCount(1, Storage::disk('local')->allFiles('lms-assets'));
        $block = $studio->draft($actor, $course)['sections'][0]['lessons'][0]['blocks'][0];
        $this->write($actor, $course, 'remove_block', ['key' => $block['key']]);
        $studio->publish($actor, $course, $course->fresh()->lock_version);
        $this->assertModelExists($resource);
        Storage::disk('local')->assertExists('resources/shared.pdf');
        $this->assertSame(1, DB::table('resources')->count());
    }

    public function test_private_assets_retain_canonical_owner_after_merge_and_privacy_removes_revisions_and_bytes(): void
    {
        Storage::fake('local');
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        [$actor,$course,$key] = $this->draft($secondary);
        $studio = app(CourseStudioService::class);
        $asset = $studio->upload($actor, $course, $this->pdf(), 'file', $course->fresh()->lock_version);
        $path = $asset->path;
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'file', 'asset_id' => $asset->id, 'label' => 'PRIVATE FILE CAPTION']);
        $course = $studio->publish($actor, $course, $course->fresh()->lock_version);
        $assignment = app(LmsAccessOperations::class)->assign($actor, $secondary, $course, ['instructions' => 'PRIVATE INSTRUCTIONS'], 'private-course');
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $actor->id);
        $this->assertSame($primary->id, $course->fresh()->owner_student_id);
        $this->assertTrue(app(LmsAccessService::class)->canAccess($primary, $assignment));
        app(StudentPrivacyService::class)->anonymize($primary->id, $actor->id);
        $this->assertSame('withdrawn', $asset->fresh()->status);
        $this->assertNull($asset->fresh()->path);
        Storage::disk('local')->assertMissing($path);
        $revisions = ContentRevision::query()->where('revisable_type', Course::class)->where('revisable_id', $course->id)->get()->toJson();
        $this->assertStringNotContainsString('PRIVATE FILE CAPTION', $revisions);
        $this->assertSame('archived', $course->fresh()->status);
        $this->assertDatabaseCount('lms_course_releases', 1);
    }

    /** @return array<string,array{string,string}> */
    public static function invalidUrls(): array
    {
        return ['script' => ['external_link', 'javascript:alert(1)'], 'credentials' => ['external_link', 'https://user:pass@example.test/file'], 'fake youtube' => ['youtube_video', 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ'], 'unsafe frame' => ['external_video', 'https://example.test/frame.html']];
    }

    #[DataProvider('invalidUrls')]
    public function test_unsafe_links_and_provider_metadata_are_rejected(string $kind, string $url): void
    {
        [$actor,$course,$key] = $this->draft();
        $this->actingAs($actor, 'web')->postJson(route('admin.lms.courses.update', $course), ['operation' => 'add_block', 'version' => $course->fresh()->lock_version, 'parent_key' => $key, 'kind' => $kind, 'url' => $url])->assertUnprocessable()->assertJsonValidationErrors('url');
        $this->assertSame([], app(CourseStudioService::class)->draft($actor, $course)['sections'][0]['lessons'][0]['blocks']);
    }

    public function test_provider_parsing_uses_controlled_embeds_and_mixed_text_is_sanitized(): void
    {
        [$actor,$course,$key] = $this->draft();
        $studio = app(CourseStudioService::class);
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'youtube_video', 'url' => 'https://youtu.be/dQw4w9WgXcQ?t=12']);
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'external_video', 'url' => 'https://vimeo.com/123456789']);
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'external_video', 'url' => 'https://example.test/lesson.mp4']);
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'rich_text', 'html' => '<p dir="rtl" onclick="alert(1)">أهلاً <strong>Hello</strong></p><img src="/storage/public.png" onerror="alert(1)"><iframe src="https://evil.test"></iframe>']);
        $graph = $studio->draft($actor, $course);
        $blocks = $graph['sections'][0]['lessons'][0]['blocks'];
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $blocks[0]['payload']['embed_url']);
        $this->assertSame('https://player.vimeo.com/video/123456789', $blocks[1]['payload']['embed_url']);
        $this->assertSame('direct', $blocks[2]['payload']['provider']);
        $this->assertStringNotContainsString('<img', $blocks[3]['payload']['html']);
        $this->assertStringNotContainsString('onclick', $blocks[3]['payload']['html']);
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.preview', $course))->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('player.vimeo.com/video/123456789', false)->assertSee('أهلاً')->assertDontSee('alert(1)', false)->assertDontSee('https://evil.test', false);
    }

    public function test_unsupported_authoring_controls_are_absent_and_server_rejects_their_submission(): void
    {
        [$actor,$course,$key] = $this->draft();
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.edit', $course))->assertDontSee('value="bunny_video"', false)->assertDontSee('value="quiz"', false)->assertDontSee('value="assignment"', false);
        $this->postJson(route('admin.lms.courses.update', $course), ['operation' => 'add_block', 'version' => $course->fresh()->lock_version, 'parent_key' => $key, 'kind' => 'quiz'])->assertUnprocessable()->assertJsonValidationErrors('kind');
    }

    public function test_missing_or_modified_bytes_fail_closed_in_preview_and_publication(): void
    {
        Storage::fake('local');
        [$actor,$course,$key] = $this->draft();
        $studio = app(CourseStudioService::class);
        $asset = $studio->upload($actor,$course,$this->pdf(),'file',$course->fresh()->lock_version);
        $this->write($actor,$course,'add_block',['parent_key' => $key, 'kind' => 'file', 'asset_id' => $asset->id]);
        Storage::disk('local')->put($asset->path,'REPLACED BYTES');
        $this->actingAs($actor,'web')->get(route('admin.lms.courses.assets',[$course, $asset]))->assertNotFound();
        $this->postJson(route('admin.lms.courses.lifecycle',$course),['status' => 'published', 'version' => $course->fresh()->lock_version])->assertUnprocessable()->assertJsonValidationErrors('publication');
        $this->assertSame('draft',$course->fresh()->status);
    }
}
