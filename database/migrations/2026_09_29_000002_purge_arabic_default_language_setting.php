<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'default_language')
            ->where('value', 'ar')
            ->update([
                'value' => 'en',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversible forward migration: Arabic default language purge is permanent
    }
};
