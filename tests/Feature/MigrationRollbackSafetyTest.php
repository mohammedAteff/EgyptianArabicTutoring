<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\CMS\Models\Page;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRollbackSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_down_throws_exception_and_preserves_data_when_authored_content_exists(): void
    {
        // 1. Create a newly admin-authored English entity created after backfill
        $admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $adminPage = Page::create([
            'title' => 'Admin Authored Policy',
            'slug' => 'admin-authored-policy',
            'content' => 'Authored content here',
            'status' => 'published',
            'published_at' => now(),
        ]);

        DB::table('entity_translation_revisions')->insert([
            'entity_type' => 'page',
            'entity_id' => $adminPage->id,
            'revision_number' => 1,
            'locale' => 'en',
            'title' => 'Admin Authored Policy',
            'description' => 'Authored content here',
            'content' => json_encode(['title' => 'Admin Authored Policy', 'content' => 'Authored content here']),
            'created_by' => $admin->id, // Authored by admin
            'created_at' => now(),
        ]);

        DB::table('page_translations')->insert([
            'page_id' => $adminPage->id,
            'locale' => 'en',
            'source_revision_id' => 1,
            'status' => 'published',
            'title' => 'Admin Authored Policy',
            'content' => 'Authored content here',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add a French translation for the page
        DB::table('page_translations')->insert([
            'page_id' => $adminPage->id,
            'locale' => 'fr',
            'source_revision_id' => 1,
            'status' => 'draft',
            'title' => 'Politique redigee par admin',
            'content' => 'Contenu ici',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Calling migration 4 down() MUST throw a RuntimeException to prevent silent deletion
        $migration4 = require database_path('migrations/2026_09_19_000004_migrate_english_content_to_translations.php');

        $caught4 = false;
        try {
            $migration4->down();
        } catch (\RuntimeException $e) {
            $caught4 = true;
            $this->assertStringContainsString('Cannot rollback migration', $e->getMessage());
        }
        $this->assertTrue($caught4, 'Migration 4 down() must throw RuntimeException when authored content exists');

        // Verify authored English row survives intact
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $adminPage->id,
            'locale' => 'en',
            'title' => 'Admin Authored Policy',
        ]);

        // Verify revision snapshot survives intact
        $this->assertDatabaseHas('entity_translation_revisions', [
            'entity_type' => 'page',
            'entity_id' => $adminPage->id,
            'created_by' => $admin->id,
        ]);

        // Verify French translation survives intact
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $adminPage->id,
            'locale' => 'fr',
            'status' => 'draft',
        ]);

        // 3. Calling migration 3 down() MUST also throw RuntimeException and NOT drop tables
        $migration3 = require database_path('migrations/2026_09_19_000003_create_translation_and_revision_tables.php');

        $caught3 = false;
        try {
            $migration3->down();
        } catch (\RuntimeException $e) {
            $caught3 = true;
            $this->assertStringContainsString('Cannot rollback migration', $e->getMessage());
        }
        $this->assertTrue($caught3, 'Migration 3 down() must throw RuntimeException when authored content exists');

        $this->assertTrue(Schema::hasTable('page_translations'), 'page_translations table must not be dropped');
        $this->assertTrue(Schema::hasTable('entity_translation_revisions'), 'entity_translation_revisions table must not be dropped');

        // 4. Running migrate command succeeds without duplicate table error
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(true, 'Migrate completes without error');
    }

    public function test_pristine_seed_rollback_cleans_up_when_no_authored_content_exists(): void
    {
        // Truncate translation tables to simulate pristine unedited seed state
        DB::table('page_translations')->truncate();
        DB::table('resource_translations')->truncate();
        DB::table('game_translations')->truncate();
        DB::table('faq_translations')->truncate();
        DB::table('category_translations')->truncate();
        DB::table('entity_translation_revisions')->truncate();

        $category = ResourceCategory::create([
            'name' => 'Grammar Guides',
            'slug' => 'grammar-guides',
            'active' => true,
        ]);

        $unreferencedResource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Basic Vocabulary',
            'slug' => 'basic-vocab',
            'file_path' => 'resources/vocab.pdf',
            'file_name' => 'vocab.pdf',
            'file_type' => 'pdf',
            'file_size' => 512,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Insert purely initial seeded revision 1 (created_by is null)
        DB::table('entity_translation_revisions')->insert([
            'entity_type' => 'resource',
            'entity_id' => $unreferencedResource->id,
            'revision_number' => 1,
            'locale' => 'en',
            'title' => 'Basic Vocabulary',
            'created_by' => null,
            'created_at' => now(),
        ]);

        DB::table('resource_translations')->insert([
            'resource_id' => $unreferencedResource->id,
            'locale' => 'en',
            'source_revision_id' => 1,
            'status' => 'published',
            'title' => 'Basic Vocabulary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // In a pristine seed state with no authored rows, migration 4 down() safely removes seeds
        $migration4 = require database_path('migrations/2026_09_19_000004_migrate_english_content_to_translations.php');
        $migration4->down();

        $this->assertDatabaseMissing('resource_translations', [
            'resource_id' => $unreferencedResource->id,
            'locale' => 'en',
        ]);
        $this->assertDatabaseMissing('entity_translation_revisions', [
            'entity_type' => 'resource',
            'entity_id' => $unreferencedResource->id,
        ]);

        // Then migration 3 down() safely drops empty tables
        $migration3 = require database_path('migrations/2026_09_19_000003_create_translation_and_revision_tables.php');
        $migration3->down();

        $this->assertFalse(Schema::hasTable('resource_translations'));
        $this->assertFalse(Schema::hasTable('entity_translation_revisions'));
    }

    public function test_artisan_rollback_refuses_destructive_reversal_when_reconciliation_audits_or_visitor_tokens_exist(): void
    {
        // Populate audit record
        DB::table('analytics_reconciliation_audits')->insert([
            'audit_type' => 'test_rebuild',
            'rebuilt_visitors_count' => 10,
            'preserved_historical_count' => 5,
            'authoritative_bookings_count' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $threw = false;
        try {
            Artisan::call('migrate:rollback', ['--step' => 1]);
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertStringContainsString('Cannot rollback migration 2026_09_19_000007', $e->getMessage());
        }

        $this->assertTrue($threw, 'Artisan migrate:rollback must throw RuntimeException when audits exist');
        $this->assertTrue(Schema::hasTable('analytics_reconciliation_audits'), 'analytics_reconciliation_audits must not be dropped');
        $this->assertTrue(Schema::hasColumn('bookings', 'visitor_token'), 'bookings.visitor_token must not be dropped');
        $this->assertEquals(1, DB::table('analytics_reconciliation_audits')->count(), 'Audit record must survive intact');

        // Verify migration record is retained in the migrations ledger
        $this->assertDatabaseHas('migrations', [
            'migration' => '2026_09_19_000007_add_visitor_token_to_bookings_and_reconciliation_audits',
        ]);
    }

    public function test_artisan_rollback_refuses_destructive_reversal_when_marketing_touches_exist(): void
    {
        // Clean batch 8 so we can rollback to batch 6
        DB::table('analytics_reconciliation_audits')->truncate();
        DB::table('bookings')->update(['visitor_token' => null]);
        Artisan::call('migrate:rollback', ['--step' => 1]); // rolls back 000007 cleanly

        // Rollback batch 7 (000006) cleanly
        Artisan::call('migrate:rollback', ['--step' => 1]); // rolls back 000006 cleanly

        // Now we are at batch 6 (000005). Populate marketing_touches
        $visitor = Visitor::create([
            'visitor_token' => 'vis-rollback-safety',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'is_bot' => false,
        ]);

        DB::table('marketing_touches')->insert([
            'visitor_id' => $visitor->id,
            'visitor_token' => 'vis-rollback-safety',
            'utm_source' => 'twitter',
            'touch_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $threw = false;
        try {
            Artisan::call('migrate:rollback', ['--step' => 1]);
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertStringContainsString('Cannot rollback migration 2026_09_19_000005', $e->getMessage());
        }

        $this->assertTrue($threw, 'Artisan migrate:rollback must throw RuntimeException when marketing touches exist');
        $this->assertTrue(Schema::hasTable('marketing_touches'), 'marketing_touches must not be dropped');
        $this->assertTrue(Schema::hasColumn('bookings', 'touch_at'), 'bookings.touch_at must not be dropped');
        $this->assertEquals(1, DB::table('marketing_touches')->count(), 'Marketing touch record must survive intact');

        // Verify migration record is retained in the migrations ledger
        $this->assertDatabaseHas('migrations', [
            'migration' => '2026_09_19_000005_add_attribution_and_marketing_touches',
        ]);

        // Re-run migrations to restore batch state for subsequent tests
        Artisan::call('migrate');
    }
}
