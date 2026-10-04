<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Rules\SafeLessonUrl;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonWorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function roles(): array
    {
        return ['tutor' => ['super_admin', true], 'admin' => ['admin', true], 'assistant' => ['assistant', false]];
    }

    #[DataProvider('roles')]
    public function test_policy_matrix_for_staff_workspaces_and_materials(string $role, bool $allowed): void
    {
        $admin = AdministratorFactory::new()->create(['role' => $role]);
        $booking = $this->booking(Student::factory()->verified()->create());
        $material = LessonMaterial::factory()->for($booking)->create();
        $this->assertSame($allowed, Gate::forUser($admin)->allows('manageLessonWorkspace', $booking));
        $this->assertSame($allowed, Gate::forUser($admin)->allows('viewLessonWorkspace', $booking));
        $this->assertSame($allowed, Gate::forUser($admin)->allows('view', $material));
    }

    #[DataProvider('roles')]
    public function test_only_permitted_staff_can_attach_material(string $role, bool $allowed): void
    {
        $admin = AdministratorFactory::new()->create(['role' => $role]);
        $booking = $this->booking(Student::factory()->verified()->create());
        $response = $this->actingAs($admin)->post(route('admin.lessons.materials.store', $booking), $this->link());
        if (! $allowed) {
            $response->assertForbidden();
            $this->assertDatabaseCount('lesson_materials', 0);

            return;
        }
        $response->assertRedirect(route('admin.lessons.show', $booking))->assertSessionHasNoErrors();
        $material = $booking->lessonMaterials()->sole();
        $this->assertSame($admin->id, $material->created_by);
        $this->assertFalse($material->student_visible);
        $this->assertSame('external_link', $material->kind);
        $audit = AuditLog::query()->where('action', 'lesson_material_attached')->sole();
        $this->assertSame($admin->id, $audit->administrator_id);
        $this->assertStringNotContainsString('https://', json_encode($audit->new_data));
    }

    public function test_assistant_cannot_read_update_withdraw_or_download_preparation(): void
    {
        $booking = $this->booking(Student::factory()->verified()->create());
        $material = LessonMaterial::factory()->for($booking)->create();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']));
        $this->get(route('admin.lessons.show', $booking))->assertForbidden();
        $this->patch(route('admin.lessons.materials.update', [$booking, $material]), $this->edit())->assertForbidden();
        $this->delete(route('admin.lessons.materials.destroy', [$booking, $material]))->assertForbidden();
        $this->get(route('admin.lessons.materials.open', [$booking, $material]))->assertForbidden();
        $this->assertNull($material->fresh()->withdrawn_at);
    }

    public function test_guests_and_suspended_staff_cannot_manage_materials(): void
    {
        $booking = $this->booking(Student::factory()->verified()->create());
        $this->get(route('admin.lessons.show', $booking))->assertRedirect();
        $admin = AdministratorFactory::new()->create(['suspended_at' => now()]);
        $this->assertFalse(Gate::forUser($admin)->allows('manageLessonWorkspace', $booking));
    }

    public function test_private_pdf_upload_download_headers_and_opaque_storage(): void
    {
        Storage::fake('local');
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        $admin = AdministratorFactory::new()->create();
        $this->actingAs($admin)->post(route('admin.lessons.materials.store', $booking), [
            'kind' => 'private_file', 'title' => 'My "notes" / lesson', 'sort_order' => 0, 'student_visible' => 1,
            'file' => UploadedFile::fake()->createWithContent('user-notes.pdf', $this->pdf()),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $material = $booking->lessonMaterials()->sole();
        $this->assertMatchesRegularExpression('~^lesson-materials/[0-9a-f-]{36}\.pdf$~', $material->path);
        Storage::disk('local')->assertExists($material->path);
        $this->assertArrayNotHasKey('path', $material->toArray());
        $this->portal($student);
        $download = $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]));
        $download->assertDownload('my-notes-lesson.pdf')->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertSame($this->pdf(), file_get_contents($download->baseResponse->getFile()->getPathname()));
        $this->get('/storage/'.$material->path)->assertForbidden();
    }

    public static function unsafeUploads(): array
    {
        return [
            'html' => ['notes.pdf', '<html><script>alert(1)</script></html>'],
            'script suffix' => ['notes.pdf', "%PDF-1.4\n<script>bad</script>\n%%EOF"],
            'incomplete' => ['notes.pdf', "%PDF-1.4\nbroken"],
            'active PDF' => ['notes.pdf', "%PDF-1.4\n1 0 obj <</JS (alert(1))>> endobj\n%%EOF"],
            'obfuscated PDF action' => ['notes.pdf', "%PDF-1.4\n1 0 obj <</J#53 (bad)>> endobj\n%%EOF"],
            'wrong extension' => ['notes.html', "%PDF-1.4\n1 0 obj <<>> endobj\n%%EOF"],
        ];
    }

    #[DataProvider('unsafeUploads')]
    public function test_rejects_unsafe_pdf_content_and_extension(string $name, string $bytes): void
    {
        Storage::fake('local');
        $booking = $this->booking(Student::factory()->verified()->create());
        $this->actingAs(AdministratorFactory::new()->create())->post(route('admin.lessons.materials.store', $booking), [
            'kind' => 'private_file', 'title' => 'Notes', 'sort_order' => 0, 'student_visible' => 0,
            'file' => UploadedFile::fake()->createWithContent($name, $bytes),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('lesson_materials', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_rejects_oversized_pdf_without_writing_file(): void
    {
        Storage::fake('local');
        $booking = $this->booking(Student::factory()->verified()->create());
        $this->actingAs(AdministratorFactory::new()->create())->post(route('admin.lessons.materials.store', $booking), [
            'kind' => 'private_file', 'title' => 'Notes', 'sort_order' => 0, 'student_visible' => 0,
            'file' => UploadedFile::fake()->create('notes.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('lesson_materials', 0);
    }

    public static function unsafeUrls(): array
    {
        return array_map(fn ($url) => [$url], [
            'javascript:alert(1)', 'data:text/html,hello', 'http://example.org', '//example.org',
            'https://user:password@example.org/video', "https://example.org/\r\nheader",
            'https://example.org/%0d%0aheader', 'https://example.org\\evil',
        ]);
    }

    #[DataProvider('unsafeUrls')]
    public function test_https_rule_rejects_unsafe_links(string $url): void
    {
        $validator = Validator::make(['url' => $url], ['url' => [new SafeLessonUrl]]);
        $this->assertTrue($validator->fails());
        $this->assertSame('Use a valid HTTPS link without embedded login credentials.', $validator->errors()->first('url'));
    }

    public function test_link_validation_wiring_and_safe_token_url_audit(): void
    {
        Http::preventStrayRequests();
        $booking = $this->booking(Student::factory()->verified()->create());
        $this->actingAs(AdministratorFactory::new()->create())->post(route('admin.lessons.materials.store', $booking),
            $this->link(['url' => 'javascript:alert(1)']))->assertSessionHasErrors('url');
        $this->post(route('admin.lessons.materials.store', $booking), $this->link([
            'kind' => 'recording', 'url' => 'https://example.org/video?token=private-token', 'student_visible' => 1,
        ]))->assertSessionHasNoErrors();
        $audit = AuditLog::query()->where('action', 'lesson_recording_attached')->sole();
        $this->assertStringNotContainsString('private-token', $audit->toJson());
        $this->assertStringNotContainsString('example.org', $audit->toJson());
        Http::assertNothingSent();
    }

    public function test_payload_fields_cannot_change_parent_creator_or_payload_combination(): void
    {
        $booking = $this->booking(Student::factory()->verified()->create());
        $this->actingAs(AdministratorFactory::new()->create())->post(route('admin.lessons.materials.store', $booking), $this->link([
            'path' => '../../.env', 'disk' => 'public', 'booking_id' => 900, 'created_by' => 900, 'resource_id' => 999,
        ]))->assertSessionHasErrors(['path', 'disk', 'booking_id', 'created_by', 'resource_id']);
        $this->assertDatabaseCount('lesson_materials', 0);
    }

    public static function incompatiblePayloads(): array
    {
        return [
            'link carrying a file' => [['disk' => 'local', 'path' => 'lesson-materials/a.pdf']],
            'PDF missing disk' => [['kind' => 'private_file', 'disk' => null, 'path' => 'lesson-materials/a.pdf', 'url' => null]],
            'withdrawn PDF missing disk' => [['kind' => 'private_file', 'disk' => null, 'path' => 'lesson-materials/a.pdf', 'url' => null, 'withdrawn_at' => '2026-10-01 12:00:00']],
            'PDF missing path' => [['kind' => 'private_file', 'disk' => 'local', 'path' => null, 'url' => null]],
            'resource missing reference' => [['kind' => 'resource', 'resource_id' => null, 'url' => null]],
            'recording missing URL' => [['kind' => 'recording', 'url' => null]],
            'unknown material kind' => [['kind' => 'unknown']],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('incompatiblePayloads')]
    public function test_database_rejects_incompatible_material_payloads(array $payload): void
    {
        $booking = $this->booking(Student::factory()->verified()->create());
        $this->expectException(QueryException::class);
        LessonMaterial::factory()->for($booking)->create($payload);
    }

    public function test_history_shows_multiple_ordered_visible_items_and_omits_private_recording(): void
    {
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        LessonMaterial::factory()->for($booking)->visible()->create(['title' => 'Second worksheet', 'sort_order' => 9]);
        LessonMaterial::factory()->for($booking)->visible()->create(['title' => '<script>First worksheet</script>', 'sort_order' => 1]);
        LessonMaterial::factory()->for($booking)->create(['kind' => 'recording', 'title' => 'Private recording']);
        $this->portal($student);
        $this->get(route('student.dashboard'))->assertSeeInOrder(['First worksheet', 'Second worksheet'])
            ->assertDontSee('<script>First worksheet</script>', false)->assertDontSee('Private recording')
            ->assertDontSee('Recording')->assertDontSee('No recording available');
    }

    public function test_visible_recording_is_separate_and_safe_external_link_has_no_referrer(): void
    {
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        $material = LessonMaterial::factory()->for($booking)->visible()->create(['kind' => 'recording', 'title' => 'Watch session']);
        $this->portal($student);
        $this->get(route('student.dashboard'))->assertSee('Recording')->assertSee('Watch session')->assertSee('rel="noopener noreferrer"', false);
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))
            ->assertRedirect('https://example.org/lesson')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public static function ineligibleLessons(): array
    {
        return ['future' => ['confirmed', 2], 'current' => ['confirmed', 0], 'cancelled' => ['cancelled', -2],
            'no show' => ['no_show', -2], 'held' => ['held', -2], 'pending' => ['pending', -2]];
    }

    #[DataProvider('ineligibleLessons')]
    public function test_students_cannot_access_ineligible_lessons(string $status, int $days): void
    {
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student, $status, $days);
        $material = LessonMaterial::factory()->for($booking)->visible()->create(['title' => 'Unavailable material']);
        $this->portal($student);
        $this->get(route('student.lessons.show', $booking->id))->assertNotFound();
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))->assertNotFound();
        $this->get(route('student.dashboard'))->assertDontSee('Unavailable material');
    }

    public function test_foreign_booking_material_and_hidden_pdf_are_not_found(): void
    {
        Storage::fake('local');
        $student = Student::factory()->verified()->create();
        $own = $this->booking($student);
        $foreign = $this->booking(Student::factory()->verified()->create());
        $foreignPdf = $this->storedPdf($foreign, true);
        $hidden = $this->storedPdf($own, false);
        $this->portal($student);
        $this->get(route('student.lessons.show', $foreign->id))->assertNotFound();
        $this->get(route('student.lessons.materials.open', [$foreign->id, $foreignPdf->id]))->assertNotFound();
        $this->get(route('student.lessons.materials.open', [$own->id, $foreignPdf->id]))->assertNotFound();
        $this->get(route('student.lessons.materials.open', [$own->id, $hidden->id]))->assertNotFound();
    }

    public function test_query_path_overrides_do_not_select_a_different_private_file(): void
    {
        Storage::fake('local');
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        $material = $this->storedPdf($booking, true);
        $this->portal($student);
        $response = $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]).'?path=../../.env&disk=public');
        $response->assertDownload('private-notes.pdf');
        $this->assertSame($this->pdf(), file_get_contents($response->baseResponse->getFile()->getPathname()));
        $material->update(['path' => 'lesson-materials/../../.env']);
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))->assertNotFound();
    }

    public function test_resource_reference_uses_same_private_bytes_without_public_gate(): void
    {
        Storage::fake('local');
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        Storage::disk('local')->put('resources/existing.pdf', $this->pdf());
        $resource = $this->resource(['file_path' => 'resources/existing.pdf']);
        $this->actingAs(AdministratorFactory::new()->create())->post(route('admin.lessons.materials.store', $booking),
            ['kind' => 'resource', 'resource_id' => $resource->id, 'title' => 'Library worksheet', 'sort_order' => 0, 'student_visible' => 1])->assertSessionHasNoErrors();
        $material = $booking->lessonMaterials()->sole();
        $this->assertNull($material->path);
        $this->assertSame($resource->id, $material->resource_id);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->portal($student);
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))->assertDownload('library-worksheet.pdf');
        $this->assertDatabaseCount('resource_requests', 0);
        $resource->update(['status' => 'archived']);
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))->assertNotFound();
        $this->get(route('student.dashboard'))->assertDontSee('Library worksheet');
    }

    public function test_resource_soft_delete_and_unsafe_legacy_resource_urls_fail_closed(): void
    {
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        $resource = $this->resource(['external_url' => 'javascript:alert(1)']);
        $material = LessonMaterial::factory()->for($booking)->visible()->create(['kind' => 'resource', 'url' => null, 'resource_id' => $resource->id]);
        $this->portal($student);
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))->assertNotFound();
        $resource->delete();
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))->assertNotFound();
        $this->get(route('student.dashboard'))->assertDontSee($material->title);
    }

    public function test_edit_visibility_and_order_and_withdraw_leave_all_business_records_unchanged(): void
    {
        Storage::fake('local');
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        $admin = AdministratorFactory::new()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Workspace source', 3, '90', '0', 'USD', null, Str::uuid(), $admin->id, entitlementCode: 'one_hour');
        $booking->sessionType->update(['funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
        $booking->unsetRelation('sessionType');
        $ledger->consumeForBooking($booking, Str::uuid());
        $ledger->recordPayment($package, '90', Str::uuid(), $admin->id);
        $before = $this->businessSnapshot($booking);
        $material = $this->storedPdf($booking, false);
        $this->actingAs($admin)->patch(route('admin.lessons.materials.update', [$booking, $material]), $this->edit(['student_visible' => 1, 'sort_order' => 5]))->assertSessionHasNoErrors();
        $this->assertTrue($material->fresh()->student_visible);
        $this->assertSame(5, $material->fresh()->sort_order);
        $path = $material->path;
        $this->delete(route('admin.lessons.materials.destroy', [$booking, $material]))->assertRedirect();
        $this->assertNotNull($material->fresh()->withdrawn_at);
        $this->assertFalse($material->fresh()->student_visible);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame($before, $this->businessSnapshot($booking));
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson_material_visibility_changed', 'administrator_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson_material_withdrawn', 'administrator_id' => $admin->id]);
        $this->portal($student);
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))->assertNotFound();
    }

    public function test_recording_withdrawal_clears_secret_url_and_cannot_be_republished(): void
    {
        $booking = $this->booking(Student::factory()->verified()->create());
        $material = LessonMaterial::factory()->for($booking)->visible()->create(['kind' => 'recording', 'url' => 'https://example.org/?token=secret']);
        $this->actingAs(AdministratorFactory::new()->create())->delete(route('admin.lessons.materials.destroy', [$booking, $material]))->assertRedirect();
        $this->assertNull($material->fresh()->url);
        $this->patch(route('admin.lessons.materials.update', [$booking, $material]), $this->edit())->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson_recording_withdrawn']);
    }

    public function test_withdrawal_can_retry_cleanup_without_republishing_when_disk_delete_fails(): void
    {
        Storage::fake('local');
        $booking = $this->booking(Student::factory()->verified()->create());
        $material = $this->storedPdf($booking, true);
        $disk = Storage::disk('local');
        $mock = \Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('local', $mock);
        $actor = AdministratorFactory::new()->create();
        $this->actingAs($actor)->delete(route('admin.lessons.materials.destroy', [$booking, $material]))->assertSessionHas('warning');
        $withdrawn = $material->fresh();
        $this->assertFalse($withdrawn->student_visible);
        $this->assertNotNull($withdrawn->path);
        Storage::set('local', $disk);
        $this->delete(route('admin.lessons.materials.destroy', [$booking, $withdrawn]))->assertSessionHas('success');
        $this->assertNull($material->fresh()->path);
        $disk->assertMissing($material->path);
    }

    public function test_suspended_unverified_and_deleted_students_lose_material_access(): void
    {
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        $material = LessonMaterial::factory()->for($booking)->visible()->create();
        $this->portal($student);
        $student->update(['suspended_at' => now()]);
        $this->get(route('student.lessons.materials.open', [$booking->id, $material->id]))->assertRedirect(route('student.login'));
        $student->update(['suspended_at' => null, 'identity_status' => 'legacy_unverified']);
        $this->assertFalse(Gate::forUser($student)->allows('view', $material));
        $student->delete();
        $this->assertFalse(Gate::forUser($student)->allows('viewLessonWorkspace', $booking));
    }

    public function test_merge_preserves_booking_provenance_and_transfers_only_canonical_material_access(): void
    {
        Storage::fake('local');
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $booking = $this->booking($secondary);
        $material = $this->storedPdf($booking, true);
        $id = $booking->id;
        $path = $material->path;
        app(StudentMergeService::class)->merge($primary->id, $secondary->id);
        $this->assertSame($id, $material->fresh()->booking_id);
        $this->assertSame($path, $material->fresh()->path);
        $this->assertSame($primary->id, $booking->fresh()->student_id);
        $this->portal($primary);
        $this->get(route('student.lessons.materials.open', [$id, $material->id]))->assertDownload();
        $this->portal($secondary);
        $this->get(route('student.lessons.materials.open', [$id, $material->id]))->assertRedirect(route('student.login'));
    }

    public function test_privacy_erases_material_payload_and_private_file_but_preserves_booking_and_resource(): void
    {
        Storage::fake('local');
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student);
        $material = $this->storedPdf($booking, true);
        $path = $material->path;
        $resource = $this->resource(['external_url' => 'https://example.org/shared']);
        LessonMaterial::factory()->for($booking)->visible()->create(['kind' => 'resource', 'resource_id' => $resource->id, 'url' => null]);
        LessonMaterial::factory()->for($booking)->visible()->create(['kind' => 'recording', 'url' => 'https://example.org/?secret=private', 'description' => 'Personal note']);
        $admin = AdministratorFactory::new()->create();
        app(StudentPrivacyService::class)->anonymize($student->id, $admin->id);
        $this->assertModelExists($booking);
        $this->assertModelExists($resource);
        foreach ($booking->lessonMaterials()->get() as $item) {
            $this->assertSame('Redacted lesson material', $item->title);
            $this->assertNull($item->description);
            $this->assertNull($item->url);
            $this->assertNull($item->resource_id);
            $this->assertFalse($item->student_visible);
            $this->assertNotNull($item->withdrawn_at);
            $this->assertNull($item->path);
        }
        Storage::disk('local')->assertMissing($path);
        $audit = AuditLog::query()->where('action', 'lesson_material_privacy_erased')->firstOrFail();
        $this->assertSame($admin->id, $audit->administrator_id);
        $this->assertStringNotContainsString($path, $audit->toJson());
        $this->assertStringNotContainsString('Personal note', $audit->toJson());
    }

    public function test_foreign_staff_nested_material_mutations_return_not_found(): void
    {
        $booking = $this->booking(Student::factory()->verified()->create());
        $other = $this->booking(Student::factory()->verified()->create());
        $material = LessonMaterial::factory()->for($other)->create();
        $this->actingAs(AdministratorFactory::new()->create())->patch(route('admin.lessons.materials.update', [$booking, $material]), $this->edit())->assertNotFound();
        $this->delete(route('admin.lessons.materials.destroy', [$booking, $material]))->assertNotFound();
        $this->get(route('admin.lessons.materials.open', [$booking, $material]))->assertNotFound();
        $this->assertNull($material->fresh()->withdrawn_at);
    }

    public function test_history_adds_one_material_projection_query_without_payload_loading_or_n_plus_one(): void
    {
        $student = Student::factory()->verified()->create();
        foreach (range(1, 6) as $index) {
            $booking = $this->booking($student);
            LessonMaterial::factory()->for($booking)->visible()->create();
        }
        $this->portal($student);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'lesson_materials')) {
                $queries[] = $query->sql;
            }
        });
        $response = $this->get(route('student.dashboard'))->assertOk();
        $this->assertCount(1, $queries);
        $this->assertCount(6, $response->viewData('history'));
        $first = $response->viewData('history')->first()->lessonMaterials->first();
        $this->assertArrayNotHasKey('path', $first->getAttributes());
        $this->assertArrayNotHasKey('url', $first->getAttributes());
    }

    private function portal(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }

    private function booking(Student $student, string $status = 'completed', int $days = -2): Booking
    {
        $type = SessionType::create(['title' => 'Workspace lesson', 'slug' => Str::uuid(), 'duration_minutes' => 60, 'price' => '25', 'currency' => 'USD', 'active' => true]);
        $contact = Contact::create(['name' => 'QA student', 'email' => Str::uuid().'@example.test']);
        $start = now('UTC')->addDays($days);
        $snapshot = app(TimezoneService::class)->createBookingSnapshot($start->toImmutable(), $start->copy()->addHour()->toImmutable(), 'Europe/Berlin', 'Africa/Cairo');

        return Booking::create($snapshot + ['contact_id' => $contact->id, 'student_id' => $student->id, 'session_type_id' => $type->id,
            'status' => $status, 'idempotency_key' => Str::uuid(), 'confirmation_token' => Str::random(64)]);
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    private function storedPdf(Booking $booking, bool $visible): LessonMaterial
    {
        $path = 'lesson-materials/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $this->pdf());

        return LessonMaterial::factory()->for($booking)->create(['kind' => 'private_file', 'disk' => 'local',
            'path' => $path, 'url' => null, 'title' => 'Private notes', 'student_visible' => $visible]);
    }

    private function link(array $overrides = []): array
    {
        return array_replace(['kind' => 'external_link', 'title' => 'Learning link', 'url' => 'https://example.org/lesson',
            'student_visible' => 0, 'sort_order' => 0], $overrides);
    }

    private function edit(array $overrides = []): array
    {
        return array_replace(['title' => 'Updated material', 'description' => 'Optional description', 'student_visible' => 0, 'sort_order' => 0], $overrides);
    }

    private function resource(array $overrides = []): Resource
    {
        return Resource::create(array_replace(['category_id' => ResourceCategory::create(['name' => 'QA', 'slug' => Str::uuid(), 'active' => true])->id, 'title' => 'Existing resource', 'slug' => Str::uuid(), 'status' => 'published',
            'published_at' => now()->subDay(), 'is_gated' => true], $overrides));
    }

    private function businessSnapshot(Booking $booking): array
    {
        return ['booking' => $booking->fresh()->getAttributes(),
            'packages' => DB::table('student_packages')->orderBy('id')->get()->toJson(),
            'ledger' => DB::table('session_ledger_entries')->orderBy('id')->get()->toJson(),
            'payments' => DB::table('payment_records')->orderBy('id')->get()->toJson(),
            'refunds' => DB::table('payment_refunds')->orderBy('id')->get()->toJson()];
    }
}
