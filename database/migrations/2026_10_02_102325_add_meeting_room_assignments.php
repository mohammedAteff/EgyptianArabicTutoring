<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('icon', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        foreach (['Zoom', 'Google Meet', 'Microsoft Teams'] as $index => $name) {
            DB::table('meeting_providers')->insert(['name' => $name, 'is_default' => $index === 0, 'sort_order' => $index, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('meeting_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_provider_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->text('url');
            $table->char('url_hash', 64)->unique();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('meeting_room_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('meeting_url_snapshot')->nullable();
            $table->string('meeting_provider_snapshot', 80)->nullable();
            $table->timestamp('meeting_assigned_at')->nullable();
            $table->foreignId('meeting_assigned_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->index(['meeting_room_id', 'start_at_utc', 'end_at_utc'], 'bookings_room_interval_index');
        });
        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('preferred_meeting_provider_id')->nullable()->constrained('meeting_providers')->nullOnDelete();
        });
        $this->importLegacyMeetingRoom();
    }

    public function importLegacyMeetingRoom(): void
    {
        $legacyUrl = trim((string) DB::table('settings')->where('key', 'video_meeting_url')->value('value'));
        if (filter_var($legacyUrl, FILTER_VALIDATE_URL) && strtolower((string) parse_url($legacyUrl, PHP_URL_SCHEME)) === 'https') {
            $host = strtolower((string) parse_url($legacyUrl, PHP_URL_HOST));
            $providerName = str_contains($host, 'zoom.') ? 'Zoom' : (str_contains($host, 'meet.google.') ? 'Google Meet' : (str_contains($host, 'teams.microsoft.') ? 'Microsoft Teams' : 'Existing provider'));
            $providerId = DB::table('meeting_providers')->where('name', $providerName)->value('id');
            $providerId ??= DB::table('meeting_providers')->insertGetId(['name' => $providerName, 'sort_order' => 3, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            DB::table('meeting_providers')->update(['is_default' => false]);
            DB::table('meeting_providers')->where('id', $providerId)->update(['is_default' => true]);
            $roomId = DB::table('meeting_rooms')->insertGetId(['meeting_provider_id' => $providerId, 'name' => 'Existing lesson room', 'url' => $legacyUrl, 'url_hash' => hash('sha256', $legacyUrl), 'notes' => 'Imported from the previously configured lesson link. Review the provider and room before new assignments.', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            $lastEnd = null;
            foreach (DB::table('bookings')->whereNull('deleted_at')->where('status', 'confirmed')->where('end_at_utc', '>', now('UTC'))->orderBy('start_at_utc')->orderBy('id')->get() as $booking) {
                if ($lastEnd !== null && $booking->start_at_utc < $lastEnd) {
                    continue;
                }
                DB::table('bookings')->where('id', $booking->id)->update(['meeting_room_id' => $roomId, 'meeting_url_snapshot' => $legacyUrl, 'meeting_provider_snapshot' => $providerName, 'meeting_assigned_at' => now('UTC')]);
                $lastEnd = $booking->end_at_utc;
            }
        }

    }

    public function down(): void
    {
        throw new RuntimeException('Meeting assignments contain historical snapshots and cannot be dropped automatically.');
    }
};
