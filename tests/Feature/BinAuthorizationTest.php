<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\StaffBin;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BinAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_roles_can_share_and_edit_only_their_own_notes_and_only_super_admin_can_delete(): void
    {
        $owner = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $bin = StaffBin::factory()->create(['author_id' => $owner->id, 'body' => '<script>alert(1)</script> shared vocabulary']);
        foreach (['admin', 'assistant', 'super_admin'] as $role) {
            $other = AdministratorFactory::new()->create(['role' => $role]);
            $this->actingAs($other, 'web')->get(route('admin.staff-bins.index'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
            $this->put(route('admin.staff-bins.update', $bin), ['title' => 'Forbidden', 'body' => 'Changed', 'author_id' => $other->id])->assertForbidden();
            $this->post(route('admin.staff-bins.store'), ['title' => 'Own note', 'body' => 'Own vocabulary', 'author_id' => $owner->id])->assertRedirect();
            $own = StaffBin::where('author_id', $other->id)->sole();
            $this->put(route('admin.staff-bins.update', $own), ['title' => 'Revised', 'body' => 'Updated body'])->assertRedirect();
            $this->assertSame('Updated body', $own->fresh()->body);
            if ($role !== 'super_admin') {
                $this->delete(route('admin.staff-bins.destroy', $bin), ['confirmation' => 'DELETE'])->assertForbidden();
            }
        }
        $this->actingAs($owner, 'web')->delete(route('admin.staff-bins.destroy', $bin), ['confirmation' => 'DELETE'])->assertRedirect();
        $this->assertSoftDeleted($bin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_bin_deleted', 'entity_id' => $bin->id]);
    }

    public function test_student_notes_are_isolated_hidden_notes_are_404_and_payload_cannot_change_ownership(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $visible = StudentBin::factory()->create(['student_id' => $student->id, 'title' => 'Tutor vocabulary']);
        $hidden = StudentBin::factory()->create(['student_id' => $student->id, 'student_visible' => false, 'title' => 'Private moderation']);
        $foreign = StudentBin::factory()->create(['student_id' => $other->id, 'title' => 'Foreign note']);
        $this->portal($student)->get(route('student.bins.index'))->assertOk()->assertSee('Tutor vocabulary')->assertDontSee('Private moderation')->assertDontSee('Foreign note');
        foreach ([$hidden, $foreign] as $bin) {
            $this->get(route('student.bins.edit', $bin))->assertNotFound();
            $this->put(route('student.bins.update', $bin), ['title' => 'Guess', 'body' => 'Guess'])->assertNotFound();
        }
        $this->put(route('student.bins.update', $visible), ['title' => 'No', 'body' => 'No'])->assertForbidden();
        $this->post(route('student.bins.store'), ['title' => 'My writing', 'body' => '<img src=x onerror=alert(1)>', 'student_id' => $other->id, 'created_by_type' => 'staff', 'created_by_id' => 1, 'student_visible' => false])->assertRedirect();
        $own = StudentBin::where('title', 'My writing')->sole();
        $this->assertSame($student->id, $own->student_id);
        $this->assertSame('student', $own->created_by_type);
        $this->assertTrue($own->student_visible);
        $this->get(route('student.bins.edit', $own))->assertOk();
        $this->put(route('student.bins.update', $own), ['title' => 'My writing revised', 'body' => 'New text', 'student_id' => $other->id])->assertRedirect();
        $this->assertSame($student->id, $own->fresh()->student_id);
        $this->delete('/student/notes/'.$own->id, ['confirmation' => 'DELETE'])->assertStatus(405);
        $this->get(route('admin.staff-bins.index'))->assertRedirect(route('admin.login'));
    }

    public function test_staff_student_notes_follow_parent_binding_and_moderation_policy(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $bin = StudentBin::factory()->create(['student_id' => $student->id, 'created_by_type' => 'staff', 'created_by_id' => $admin->id]);
        $this->actingAs($admin, 'web')->get(route('admin.student-bins.edit', [$student, $bin]))->assertOk();
        $this->put(route('admin.student-bins.update', [$student, $bin]), ['title' => 'Staff revised', 'body' => 'Safe body', 'student_visible' => true])->assertRedirect();
        $this->get(route('admin.student-bins.edit', [$other, $bin]))->assertNotFound();
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($assistant, 'web')->get(route('admin.student-bins.index', $student))->assertOk();
        $this->put(route('admin.student-bins.update', [$student, $bin]), ['title' => 'No', 'body' => 'No'])->assertForbidden();
        $this->delete(route('admin.student-bins.destroy', [$student, $bin]), ['confirmation' => 'DELETE'])->assertForbidden();
        $super = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($super, 'web')->delete(route('admin.student-bins.destroy', [$student, $bin]), ['confirmation' => 'DELETE'])->assertRedirect();
        $this->assertSoftDeleted($bin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_bin_deleted', 'entity_id' => $bin->id]);
    }

    public function test_invalid_note_payloads_and_suspended_portal_are_rejected(): void
    {
        $student = Student::factory()->verified()->create();
        $this->portal($student)->post(route('student.bins.store'), ['title' => str_repeat('x', 161), 'body' => str_repeat('x', 30001)])->assertSessionHasErrors(['title', 'body']);
        $student->update(['suspended_at' => now('UTC')]);
        $this->get(route('student.bins.index'))->assertRedirect(route('student.login'));
        $this->assertDatabaseCount('student_bins', 0);
    }

    public function test_valid_multibyte_notes_fit_the_database_and_round_trip_through_the_portal(): void
    {
        $student = Student::factory()->verified()->create();
        $body = str_repeat('📚', 20000);
        $this->portal($student)->post(route('student.bins.store'), ['title' => 'Unicode writing', 'body' => $body])->assertSessionHasNoErrors()->assertRedirect();
        $note = StudentBin::where('student_id', $student->id)->sole();
        $this->assertSame($body, $note->body);
        $this->get(route('student.bins.edit', $note))->assertOk()->assertSee('Unicode writing');
    }

    public function test_merge_preserves_student_note_history_and_moves_access_to_the_canonical_student(): void
    {
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $note = StudentBin::factory()->create(['student_id' => $secondary->id, 'created_by_type' => 'student', 'created_by_id' => $secondary->id, 'title' => 'Merged writing', 'body' => 'Original lesson writing']);
        app(StudentMergeService::class)->merge($primary->id, $secondary->id);
        $this->assertSame($primary->id, $note->fresh()->student_id);
        $this->assertSame($secondary->id, $note->fresh()->created_by_id);
        $this->portal($primary)->get(route('student.bins.edit', $note))->assertOk();
        $this->put(route('student.bins.update', $note), ['title' => 'Merged writing revised', 'body' => 'Canonical student revision'])->assertRedirect();
        $this->assertSame('Canonical student revision', $note->fresh()->body);
        $this->portal($secondary)->get(route('student.bins.index'))->assertRedirect(route('student.login'));
    }

    public function test_privacy_erasure_redacts_active_and_soft_deleted_student_notes_without_touching_other_students(): void
    {
        $student = Student::factory()->verified()->create();
        $active = StudentBin::factory()->create(['student_id' => $student->id, 'body' => 'Private writing']);
        $deleted = StudentBin::factory()->create(['student_id' => $student->id, 'body' => 'Deleted private writing']);
        $deleted->delete();
        $unrelated = StudentBin::factory()->create(['body' => 'Other student writing']);
        app(StudentPrivacyService::class)->anonymize($student->id);
        foreach ([$active, $deleted] as $note) {
            $redacted = StudentBin::withTrashed()->findOrFail($note->id);
            $this->assertSame('[redacted]', $redacted->body);
            $this->assertFalse($redacted->student_visible);
        }
        $this->assertSame('Other student writing', $unrelated->fresh()->body);
    }

    public function test_rollback_refuses_to_drop_populated_note_tables_even_when_the_only_note_is_soft_deleted(): void
    {
        $note = StudentBin::factory()->create();
        $note->delete();
        $migration = require database_path('migrations/2026_10_03_141923_create_staff_and_student_bins.php');
        try {
            $migration->down();
            $this->fail('Populated notes must survive a rollback attempt.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('reviewed forward migration', $exception->getMessage());
        }
        $this->assertSoftDeleted($note);
        $this->assertDatabaseCount('staff_bins', 0);
    }

    private function portal(Student $student): static
    {
        return $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }
}
