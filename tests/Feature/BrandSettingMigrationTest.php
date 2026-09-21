<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandSettingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_v2_brand_setting_exists_after_migration(): void
    {
        $this->assertSame('Egyptian Arabic with Abdallah', Setting::get('site_name'));
    }
}
