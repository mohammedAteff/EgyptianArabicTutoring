<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogPresentation;
use App\Domains\CMS\Models\Setting;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsSettings;
use App\Domains\Lms\Services\LmsVideoProfiles;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LmsOperationalPermissionsSettingsTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return ['super' => ['super_admin', ['manage', 'viewAnalytics', 'viewProgress', 'preview', 'manageSettings', 'viewSecurity']],
            'admin' => ['admin', ['manage', 'viewAnalytics', 'viewProgress', 'preview']], 'assistant' => ['assistant', []]];
    }

    #[DataProvider('roles')]
    public function test_capabilities_retain_fresh_staff_authority(string $role, array $allowed): void
    {
        $actor = AdministratorFactory::new()->create(['role' => $role]);
        foreach (['manage', 'viewAnalytics', 'viewProgress', 'preview', 'manageSettings', 'viewSecurity'] as $ability) {
            $this->assertSame(in_array($ability, $allowed, true), Gate::forUser($actor)->allows($ability, Course::class), $role.':'.$ability);
        }
        Administrator::query()->whereKey($actor->id)->update(['suspended_at' => now('UTC')]);
        foreach (['manage', 'viewAnalytics', 'viewProgress', 'preview', 'manageSettings', 'viewSecurity'] as $ability) {
            $this->assertFalse(Gate::forUser($actor)->allows($ability, Course::class), $ability);
        }
    }

    public function test_student_and_demoted_staff_cannot_use_staff_capabilities(): void
    {
        $student = Student::factory()->verified()->create();
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        Administrator::query()->whereKey($actor->id)->update(['role' => 'assistant']);
        foreach (['manage', 'viewAnalytics', 'viewProgress', 'preview', 'manageSettings', 'viewSecurity'] as $ability) {
            $this->assertFalse(Gate::forUser($student)->allows($ability, Course::class));
            $this->assertFalse(Gate::forUser($actor)->allows($ability, Course::class));
        }
    }

    public function test_settings_are_private_versioned_and_audited_without_provider_secret_duplication(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $settings = app(LmsSettings::class);
        $values = LmsSettings::DEFAULTS;
        $values['video_threshold'] = 90;
        $values['api_key'] = 'never-store-this-secret';
        $result = $settings->save($actor, $values, 0);

        $this->assertSame(1, $result['version']);
        $this->assertSame(90, $settings->values()['video_threshold']);
        $this->assertDatabaseHas('settings', ['key' => LmsSettings::KEY, 'group' => 'lms', 'is_public' => false]);
        $this->assertSame(1, Setting::query()->where('key', LmsSettings::KEY)->count());
        $this->assertStringNotContainsString('never-store-this-secret', Setting::query()->where('key', LmsSettings::KEY)->value('value'));
        $audit = AuditLog::query()->where('action', 'lms_settings_changed')->sole();
        $this->assertSame($actor->id, $audit->administrator_id);
        $this->assertNull($audit->ip_address);
        $this->assertStringNotContainsString('api_key', json_encode($audit->new_data));
        $diff = collect(app(AuditLogPresentation::class)->diff($audit));
        $this->assertSame('90', $diff->firstWhere('field', 'video threshold')['after']);
        $this->assertStringNotContainsString('never-store-this-secret', $diff->toJson());
        try {
            $settings->save($actor, LmsSettings::DEFAULTS, 0);
            $this->fail('A stale settings version must fail.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->assertSame(90, $settings->values()['video_threshold']);
        $this->assertSame(1, AuditLog::query()->where('action', 'lms_settings_changed')->count());
    }

    public function test_global_profile_cannot_weaken_private_fallback_or_explicit_overrides(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $private = ProtectionProfile::query()->where('name', 'Private')->firstOrFail();
        $premium = ProtectionProfile::query()->where('name', 'Premium')->firstOrFail();
        $member = ProtectionProfile::query()->where('name', 'Member')->firstOrFail();
        $settings = app(LmsSettings::class);
        $settings->save($actor, array_replace(LmsSettings::DEFAULTS, ['default_catalog_profile_id' => $premium->id]), 0);
        $profiles = app(LmsVideoProfiles::class);
        $catalog = Course::factory()->create();
        $personal = Course::factory()->create(['kind' => 'private', 'owner_student_id' => Student::factory()->verified()->create()->id]);
        $this->assertSame($premium->id, $profiles->effective($catalog)->id);
        $this->assertSame($private->id, $profiles->effective($personal)->id);
        $catalog->forceFill(['protection_profile_id' => $member->id])->save();
        $this->assertSame($member->id, $profiles->effective($catalog)->id);
        $section = Section::factory()->create(['course_id' => $catalog->id]);
        $lesson = Lesson::factory()->create(['course_id' => $catalog->id, 'section_id' => $section->id, 'protection_profile_id' => $private->id]);
        $this->assertSame($private->id, $profiles->effective($catalog, $lesson)->id);
        try {
            $settings->save($actor, array_replace(LmsSettings::DEFAULTS, ['default_catalog_profile_id' => ProtectionProfile::query()->where('name', 'Public')->value('id')]), 1);
            $this->fail('An unsafe default must fail.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('default_catalog_profile_id', $error->errors());
        }
        $this->assertSame(1, $settings->document()['version']);
    }

    public function test_new_authoring_snapshots_global_threshold_while_existing_rules_remain_explicit(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $settings = app(LmsSettings::class);
        $settings->save($actor, array_replace(LmsSettings::DEFAULTS, ['video_threshold' => 90]), 0);
        $studio = app(CourseStudioService::class);
        $course = $studio->create($actor, ['title' => 'Default threshold', 'slug' => 'default-threshold', 'kind' => 'catalog']);
        $course = $studio->write($actor, $course, 'add_section', ['title' => 'Practice', 'status' => 'published'], $course->lock_version);
        $section = $studio->draft($actor, $course)['sections'][0];
        $course = $studio->write($actor, $course, 'add_lesson', ['title' => 'First', 'slug' => 'first', 'status' => 'published', 'parent_key' => $section['key']], $course->lock_version);
        $this->assertSame(90, $studio->draft($actor, $course)['sections'][0]['lessons'][0]['learning_rules']['video_threshold']);
        $settings->save($actor, array_replace(LmsSettings::DEFAULTS, ['video_threshold' => 80]), 1);
        $this->assertSame(90, $studio->draft($actor, $course)['sections'][0]['lessons'][0]['learning_rules']['video_threshold']);
        $course = $studio->write($actor, $course, 'add_lesson', ['title' => 'Second', 'slug' => 'second', 'status' => 'published', 'parent_key' => $section['key']], $course->lock_version);
        $this->assertSame(80, $studio->draft($actor, $course)['sections'][0]['lessons'][1]['learning_rules']['video_threshold']);
    }
}
