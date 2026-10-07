<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_video_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24)->unique();
            $table->unsignedBigInteger('library_id')->nullable();
            $table->string('cdn_hostname')->nullable();
            $table->text('api_key')->nullable();
            $table->text('read_only_key')->nullable();
            $table->text('signing_key')->nullable();
            $table->text('account_key')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('lock_version')->default(1);
            $table->dateTime('last_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('lms_protection_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 24)->unique();
            $table->boolean('authentication_required')->default(true);
            $table->boolean('secure_playback')->default(true);
            $table->boolean('downloads_allowed')->default(false);
            $table->unsignedSmallInteger('device_limit')->nullable();
            $table->unsignedSmallInteger('stream_limit')->nullable();
            $table->unsignedSmallInteger('token_seconds')->default(120);
            $table->unsignedSmallInteger('lease_seconds')->default(150);
            $table->unsignedSmallInteger('heartbeat_seconds')->default(30);
            $table->boolean('watermark')->default(false);
            $table->enum('drm_mode', ['none', 'optional', 'required'])->default('none');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE lms_protection_profiles ADD CONSTRAINT lms_profile_limits CHECK ((device_limit IS NULL OR device_limit BETWEEN 1 AND 20) AND (stream_limit IS NULL OR stream_limit BETWEEN 1 AND 10) AND token_seconds BETWEEN 30 AND 600 AND heartbeat_seconds BETWEEN 15 AND 60 AND lease_seconds >= token_seconds + heartbeat_seconds AND lease_seconds <= 900)');
        foreach (['Public', 'Member', 'Private', 'Premium'] as $name) {
            DB::table('lms_protection_profiles')->insert(['name' => $name, 'authentication_required' => $name !== 'Public', 'secure_playback' => true, 'downloads_allowed' => false,
                'device_limit' => $name === 'Public' ? null : ($name === 'Member' ? 4 : 2), 'stream_limit' => $name === 'Public' ? null : ($name === 'Member' ? 2 : 1),
                'token_seconds' => 120, 'lease_seconds' => 150, 'heartbeat_seconds' => 30, 'watermark' => in_array($name, ['Private', 'Premium'], true), 'drm_mode' => $name === 'Premium' ? 'optional' : 'none',
                'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
        }
        Schema::create('lms_video_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $table->enum('provider', ['bunny', 'youtube', 'external']);
            $table->foreignId('provider_connection_id')->nullable()->constrained('lms_video_providers')->restrictOnDelete();
            $table->unsignedBigInteger('library_id')->nullable();
            $table->uuid('provider_video_id')->nullable();
            $table->string('external_url', 2000)->nullable();
            $table->string('label', 200);
            $table->enum('status', ['uploading', 'processing', 'ready', 'failed', 'withdrawn', 'deleting', 'deleted', 'delete_failed'])->default('uploading');
            $table->unsignedTinyInteger('provider_status')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('has_mp4_fallback')->default(false);
            $table->uuid('upload_request_key')->unique();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->dateTime('reconciled_at')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['provider', 'library_id', 'provider_video_id'], 'lms_video_remote_identity');
            $table->index(['course_id', 'status', 'id']);
        });
        DB::statement("ALTER TABLE lms_video_assets ADD CONSTRAINT lms_video_provider_shape CHECK ((provider = 'bunny' AND provider_connection_id IS NOT NULL AND library_id IS NOT NULL AND external_url IS NULL) OR (provider IN ('youtube','external') AND provider_connection_id IS NULL AND library_id IS NULL AND provider_video_id IS NULL AND (external_url IS NOT NULL OR status IN ('withdrawn','deleted'))))");
        foreach (['lms_courses', 'lms_lessons'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('protection_profile_id')->nullable()->constrained('lms_protection_profiles')->restrictOnDelete();
            });
        }
        Schema::table('lms_lesson_blocks', function (Blueprint $table): void {
            $table->foreignId('video_asset_id')->nullable()->constrained('lms_video_assets')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE lms_lesson_blocks DROP CONSTRAINT lms_block_payload');
        DB::statement("ALTER TABLE lms_lesson_blocks ADD CONSTRAINT lms_block_payload CHECK (
            kind IN ('video','bunny_video','youtube_video','external_video','rich_text','image','file','resource','external_link','quiz','assignment')
            AND ((status IN ('placeholder','withdrawn') AND resource_id IS NULL AND asset_id IS NULL AND video_asset_id IS NULL AND payload IS NULL)
            OR (status = 'ready' AND (
                (kind = 'video' AND video_asset_id IS NOT NULL AND resource_id IS NULL AND asset_id IS NULL AND payload IS NULL)
                OR (video_asset_id IS NULL AND (
                    (kind = 'resource' AND resource_id IS NOT NULL AND asset_id IS NULL AND payload IS NULL)
                    OR (kind = 'file' AND ((resource_id IS NOT NULL AND asset_id IS NULL) OR (resource_id IS NULL AND asset_id IS NOT NULL)) AND payload IS NOT NULL)
                    OR (kind = 'image' AND resource_id IS NULL AND asset_id IS NOT NULL AND payload IS NOT NULL)
                    OR (kind IN ('rich_text','external_link','youtube_video','external_video') AND resource_id IS NULL AND asset_id IS NULL AND payload IS NOT NULL)))))))");
        Schema::create('lms_authorized_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('label', 80);
            $table->enum('status', ['authorized', 'revoked'])->default('authorized');
            $table->dateTime('last_seen_at');
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'student_id'], 'lms_device_owner');
            $table->index(['student_id', 'status', 'id']);
        });
        Schema::create('lms_playback_leases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->unsignedBigInteger('device_id');
            $table->foreign(['device_id', 'student_id'], 'lms_lease_device_owner')->references(['id', 'student_id'])->on('lms_authorized_devices')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('lms_courses')->restrictOnDelete();
            $table->unsignedBigInteger('lesson_id');
            $table->foreign(['lesson_id', 'course_id'], 'lms_lease_lesson_course')->references(['id', 'course_id'])->on('lms_lessons')->restrictOnDelete();
            $table->unsignedBigInteger('block_id');
            $table->foreign(['block_id', 'lesson_id'], 'lms_lease_block_lesson')->references(['id', 'lesson_id'])->on('lms_lesson_blocks')->restrictOnDelete();
            $table->foreignId('video_asset_id')->constrained('lms_video_assets')->restrictOnDelete();
            $table->foreignId('profile_id')->constrained('lms_protection_profiles')->restrictOnDelete();
            $table->unsignedInteger('profile_version');
            $table->char('session_hash', 64);
            $table->char('lease_token_hash', 64)->unique();
            $table->uuid('request_key');
            $table->enum('status', ['active', 'closed', 'revoked', 'expired'])->default('active');
            $table->dateTime('authorized_until');
            $table->dateTime('expires_at');
            $table->dateTime('last_heartbeat_at');
            $table->string('session_code', 12);
            $table->timestamps();
            $table->unique(['student_id', 'request_key'], 'lms_lease_request');
            $table->index(['student_id', 'authorized_until', 'expires_at', 'id'], 'lms_lease_quota');
            $table->index(['device_id', 'status', 'id']);
        });
        Schema::create('lms_video_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_asset_id')->constrained('lms_video_assets')->restrictOnDelete();
            $table->char('receipt_hash', 64)->unique();
            $table->unsignedTinyInteger('reported_status');
            $table->dateTime('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['lms_video_webhook_receipts', 'lms_playback_leases', 'lms_authorized_devices', 'lms_video_assets', 'lms_protection_profiles', 'lms_video_providers'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Populated LMS video/security data requires reviewed recovery; destructive rollback refused.');
            }
        }
        DB::statement('ALTER TABLE lms_lesson_blocks DROP CONSTRAINT lms_block_payload');
        Schema::table('lms_lesson_blocks', fn (Blueprint $table) => $table->dropConstrainedForeignId('video_asset_id'));
        foreach (['lms_courses', 'lms_lessons'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('protection_profile_id'));
        }
        foreach (['lms_video_webhook_receipts', 'lms_playback_leases', 'lms_authorized_devices', 'lms_video_assets', 'lms_protection_profiles', 'lms_video_providers'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement("ALTER TABLE lms_lesson_blocks ADD CONSTRAINT lms_block_payload CHECK (
            kind IN ('bunny_video','youtube_video','external_video','rich_text','image','file','resource','external_link','quiz','assignment')
            AND ((status IN ('placeholder','withdrawn') AND resource_id IS NULL AND asset_id IS NULL AND payload IS NULL)
            OR (status = 'ready' AND (
                (kind = 'resource' AND resource_id IS NOT NULL AND asset_id IS NULL AND payload IS NULL)
                OR (kind = 'file' AND ((resource_id IS NOT NULL AND asset_id IS NULL) OR (resource_id IS NULL AND asset_id IS NOT NULL)) AND payload IS NOT NULL)
                OR (kind = 'image' AND resource_id IS NULL AND asset_id IS NOT NULL AND payload IS NOT NULL)
                OR (kind IN ('rich_text','external_link','youtube_video','external_video') AND resource_id IS NULL AND asset_id IS NULL AND payload IS NOT NULL)))))");
    }
};
