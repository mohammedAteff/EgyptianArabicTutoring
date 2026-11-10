<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentBookmark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class QuickNotesTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(?Student $student = null): Student
    {
        $student ??= Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $this->withCredentials();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->withHeader('X-Quick-Notes-Session', Crypt::encryptString(json_encode(['student_id' => $student->id, 'session_id' => session()->getId()])));

        return $student;
    }

    /** @param array<string, string> $messages */
    #[TestWith(['en', ['Quick Notes' => 'Quick Notes', 'Saved' => 'Saved', 'Saving...' => 'Saving...', 'Retry' => 'Retry', 'Remove Bookmark' => 'Remove Bookmark', 'Copy note text' => 'Copy note text'], 'Write something before saving. Your last saved note is unchanged.'])]
    #[TestWith(['fr', ['Quick Notes' => 'Notes rapides', 'Saved' => 'Enregistré', 'Saving...' => 'Enregistrement…', 'Retry' => 'Réessayer', 'Remove Bookmark' => 'Retirer des favoris', 'Copy note text' => 'Copier le texte de la note', 'Bookmark could not be confirmed. Retry to apply your choice.' => 'La modification des favoris n’a pas pu être confirmée. Réessayez pour appliquer votre choix.', 'Your notes or bookmarks changed. Refresh this listing when your page work is saved.' => 'Vos notes ou favoris ont changé. Actualisez cette liste après avoir enregistré votre travail sur cette page.'], 'Écrivez quelque chose avant d’enregistrer. Votre dernière note enregistrée reste inchangée.'])]
    #[TestWith(['de', ['Quick Notes' => 'Schnellnotizen', 'Saved' => 'Gespeichert', 'Saving...' => 'Wird gespeichert…', 'Retry' => 'Erneut versuchen', 'Remove Bookmark' => 'Lesezeichen entfernen', 'Copy note text' => 'Notiztext kopieren'], 'Schreiben Sie etwas, bevor Sie speichern. Ihre zuletzt gespeicherte Notiz bleibt unverändert.'])]
    public function test_quick_notes_ui_and_json_errors_keep_the_selected_language(string $locale, array $messages, string $blankMessage): void
    {
        $this->signIn();
        $response = $this->get(route('student.bins.index', ['lang' => $locale]))->assertOk()->assertSee('lang="'.$locale.'"', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $script = (new \DOMXPath($document))->query('//script[@id="ui-translations"]')->item(0);
        $this->assertNotNull($script);
        $translations = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
        foreach ($messages as $source => $translated) {
            $this->assertSame($translated, $translations[$source], $source);
        }
        $this->assertSame(['en' => 'Copied', 'fr' => 'Copié', 'de' => 'Kopiert'][$locale], $translations['Copied']);

        $body = "مُراجعة — My original study note\nBonjour, Guten Tag!";
        $id = $this->postJson(route('student.quick-notes.store'), ['body' => $body, 'creation_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('note.body', $body)->json('note.id');
        $this->putJson(route('student.quick-notes.update', $id), ['body' => "\u{00A0}\u{2003}", 'revision' => 1])->assertUnprocessable()->assertJsonPath('errors.body.0', $blankMessage);

        $this->assertSame($body, StudentBin::findOrFail($id)->body);
    }

    public function test_creates_one_canonical_note_and_replays_creation_without_overwriting_later_edits(): void
    {
        $student = $this->signIn();
        $key = (string) Str::uuid();
        $body = "\nأَهْلًا — Bonjour!\nMixed Arabic / Latin.\n";
        $response = $this->postJson(route('student.quick-notes.store'), ['body' => $body, 'creation_id' => $key, 'student_id' => 999, 'created_by_type' => 'staff', 'student_visible' => false])->assertOk()->assertJsonPath('note.body', $body)->assertJsonPath('note.title', 'أَهْلًا — Bonjour!')->assertJsonPath('note.revision', 1);
        $id = $response->json('note.id');
        $this->assertDatabaseHas('student_bins', ['id' => $id, 'student_id' => $student->id, 'created_by_type' => 'student', 'student_visible' => true]);
        $this->putJson(route('student.quick-notes.update', $id), ['body' => 'Revised', 'revision' => 1])->assertOk()->assertJsonPath('note.revision', 2);
        $this->postJson(route('student.quick-notes.store'), ['body' => $body, 'creation_id' => $key])->assertOk()->assertJsonPath('note.id', $id)->assertJsonPath('note.body', 'Revised');
        $this->assertDatabaseCount('student_bins', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_bin_created', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_bin_updated', 'entity_id' => $id]);
        $this->get(route('student.bins.index'))->assertSee('Revised');
        StudentBin::findOrFail($id)->delete();
        $this->postJson(route('student.quick-notes.store'), ['body' => $body, 'creation_id' => $key])->assertStatus(410);
        $this->assertSoftDeleted('student_bins', ['id' => $id]);
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(["\n\t\u{00A0}\u{2003}"])]
    public function test_rejects_blank_creation_without_creating_notes(string $body): void
    {
        $this->signIn();
        $this->postJson(route('student.quick-notes.store'), ['body' => $body, 'creation_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('student_bins', 0);
    }

    public function test_limits_unicode_titles_and_bodies_and_retains_saved_body_after_invalid_clear(): void
    {
        $this->signIn();
        $text = str_repeat('ع', 170)."\n".str_repeat('📚', 29829);
        $response = $this->postJson(route('student.quick-notes.store'), ['body' => $text, 'creation_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('note.title', str_repeat('ع', 160));
        $id = $response->json('note.id');
        $this->putJson(route('student.quick-notes.update', $id), ['body' => '', 'revision' => 1])->assertUnprocessable();
        $this->putJson(route('student.quick-notes.update', $id), ['body' => str_repeat('📚', 30001), 'revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->assertSame($text, StudentBin::findOrFail($id)->body);
        $this->postJson(route('student.quick-notes.store'), ['body' => 'Valid', 'creation_id' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('creation_id');
    }

    public function test_ordinary_and_quick_editors_reject_stale_writes_and_preserve_explicit_title_rename(): void
    {
        $this->signIn();
        $id = $this->postJson(route('student.quick-notes.store'), ['body' => 'Initial body', 'creation_id' => (string) Str::uuid()])->assertOk()->json('note.id');
        $this->put(route('student.bins.update', $id), ['title' => 'Explicit rename', 'body' => 'Ordinary edit', 'revision' => 1])->assertSessionHasNoErrors();
        $this->putJson(route('student.quick-notes.update', $id), ['body' => 'Stale body', 'revision' => 1])->assertConflict()->assertJsonPath('conflict', true);
        $this->getJson(route('student.quick-notes.show', $id))->assertOk()->assertJsonPath('note.revision', 2)->assertHeader('Cache-Control', 'no-store, private');
        $this->putJson(route('student.quick-notes.update', $id), ['body' => 'Quick edit', 'revision' => 2])->assertOk()->assertJsonPath('note.title', 'Explicit rename')->assertJsonPath('note.revision', 3);
        $this->putJson(route('student.quick-notes.update', $id), ['body' => 'Quick edit', 'revision' => 2])->assertOk()->assertJsonPath('note.revision', 3);
        $this->from(route('student.bins.edit', $id))->put(route('student.bins.update', $id), ['title' => 'Stale rename', 'body' => 'Retain this draft', 'revision' => 2])->assertSessionHasErrors('revision')->assertSessionHasInput('body', 'Retain this draft');
        $this->assertDatabaseHas('student_bins', ['id' => $id, 'body' => 'Quick edit', 'title' => 'Explicit rename', 'revision' => 3]);
        $super = Administrator::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super, 'web')->put(route('admin.student-bins.update', [StudentBin::findOrFail($id)->student_id, $id]), ['title' => 'Staff stale', 'body' => 'Stale', 'revision' => 2, 'student_visible' => true])->assertSessionHasErrors('revision');
    }

    public function test_async_bookmark_desired_state_is_idempotent_and_preserves_other_categories(): void
    {
        $student = $this->signIn();
        $id = $this->postJson(route('student.quick-notes.store'), ['body' => 'Bookmark me', 'creation_id' => (string) Str::uuid()])->assertOk()->json('note.id');
        StudentBookmark::factory()->create(['student_id' => $student->id, 'kind' => 'homework']);
        foreach ([true, true, false, false, true] as $desired) {
            $this->putJson(route('student.quick-notes.bookmark', $id), ['bookmarked' => $desired])->assertOk()->assertJsonPath('note.bookmarked', $desired);
        }
        $this->assertDatabaseCount('student_bookmarks', 2);
        $this->get(route('student.bookmarks.index'))->assertSee('Bookmark me');
        $this->assertDatabaseHas('student_bookmarks', ['student_id' => $student->id, 'kind' => 'note', 'target_id' => $id]);
    }

    public function test_foreign_hidden_and_staff_notes_remain_protected_for_all_async_actions(): void
    {
        $student = $this->signIn();
        $foreign = StudentBin::factory()->create();
        $hidden = StudentBin::factory()->create(['student_id' => $student->id, 'student_visible' => false]);
        $staff = StudentBin::factory()->create(['student_id' => $student->id]);
        foreach ([[$foreign, 404], [$hidden, 404], [$staff, 403]] as [$note, $status]) {
            $this->getJson(route('student.quick-notes.show', $note))->assertStatus($status);
            $this->putJson(route('student.quick-notes.update', $note), ['body' => 'Unauthorized', 'revision' => 1])->assertStatus($status);
            $this->putJson(route('student.quick-notes.bookmark', $note), ['bookmarked' => true])->assertStatus($status);
            $this->assertSame(1, $note->fresh()->revision);
        }
        $this->assertDatabaseCount('student_bookmarks', 0);
    }

    public function test_expired_or_changed_account_sessions_get_json_401_and_cannot_save(): void
    {
        $this->postJson(route('student.quick-notes.store'), ['body' => 'No session', 'creation_id' => (string) Str::uuid()])->assertUnauthorized();
        $this->signIn();
        $this->withSession(['student_auth_expires_at' => now('UTC')->subMinute()->toIso8601String()])->getJson(route('student.quick-notes.session'))->assertUnauthorized();
        $this->signIn();
        $this->actingAs(Student::factory()->verified()->create(), 'student')->postJson(route('student.quick-notes.store'), ['body' => 'Wrong account', 'creation_id' => (string) Str::uuid()])->assertUnauthorized();
        $this->assertDatabaseCount('student_bins', 0);
    }

    public function test_launcher_requires_normal_authenticated_portal_and_is_independent_of_whatsapp(): void
    {
        $this->get(route('student.login'))->assertDontSee('data-quick-notes', false);
        $student = $this->signIn();
        Setting::set('whatsapp_portal', false);
        $this->get(route('student.bins.index'))->assertSee('data-quick-notes-launcher', false)->assertDontSee('data-whatsapp-cta', false);
        Setting::set('whatsapp_portal', true);
        Setting::set('whatsapp_url', 'https://wa.me/12025550142');
        $this->get(route('student.bins.index'))->assertSee('data-quick-notes-launcher', false)->assertSee('data-whatsapp-cta', false);
        $this->get('/')->assertDontSee('data-quick-notes', false);
        $this->actingAs(Administrator::factory()->create(['role' => 'admin']), 'web')->get(route('admin.students.preview-portal', $student))->assertDontSee('data-quick-notes', false);
        $this->get(route('admin.content.index'))->assertDontSee('data-quick-notes', false);
        $this->get(route('student.login'))->assertDontSee('data-quick-notes', false);
    }
}
