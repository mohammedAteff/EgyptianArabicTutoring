<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Models\VideoWebhookReceipt;
use App\Domains\Students\Services\TeachingRecordService;
use App\Exceptions\VideoProviderUnavailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LmsVideoService
{
    public function __construct(private VideoDeliveryProvider $provider, private LmsVideoSettings $settings, private AuditLogService $audits, private TeachingRecordService $students) {}

    /** @param array<string,mixed> $data */
    public function createUpload(Administrator $actor, Course $course, array $data, int $version): VideoAsset
    {
        Gate::forUser($actor)->authorize('manage', $course);
        $values = Validator::make($data, ['label' => ['required', 'string', 'max:200'], 'request_key' => ['required', 'uuid']])->validate();
        $connection = $this->settings->connection();
        $shouldCreate = false;
        $asset = DB::transaction(function () use ($actor, $course, $values, $version, $connection, &$shouldCreate): VideoAsset {
            if ($course->kind === 'private') {
                $this->students->lockStudent($course->owner_student_id);
            }
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('manage', $course);
            $existing = VideoAsset::query()->where('upload_request_key', $values['request_key'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->course_id === (int) $course->id && (int) $existing->created_by === (int) $actor->id && $existing->label === $values['label'], 409);

                return $existing;
            }
            abort_unless($course->lock_version === $version, 409, 'This course changed. Reload before uploading.');
            abort_if($course->status === 'archived' || ! $connection->enabled, 409, 'Enable the media provider before uploading.');
            $asset = new VideoAsset;
            $asset->forceFill(['course_id' => $course->id, 'provider' => 'bunny', 'provider_connection_id' => $connection->id, 'library_id' => $connection->library_id,
                'label' => $values['label'], 'status' => 'uploading', 'upload_request_key' => $values['request_key'], 'created_by' => $actor->id, 'lock_version' => 1])->save();
            $course->forceFill(['lock_version' => $course->lock_version + 1])->save();
            $shouldCreate = true;

            return $asset;
        }, 3);
        if ($asset->provider_video_id !== null) {
            return $asset;
        }
        abort_unless($shouldCreate, 409, 'This upload is still initializing. Refresh its status before retrying.');
        if ($asset->status !== 'uploading') {
            throw ValidationException::withMessages(['video' => 'This attempt could not be confirmed. Review its state before creating a replacement upload.']);
        }
        try {
            $id = $this->provider->create($connection, 'LMS media '.$asset->id);
        } catch (VideoProviderUnavailable $exception) {
            VideoAsset::query()->whereKey($asset->id)->where('status', 'uploading')->update(['status' => 'failed', 'failure_code' => 'create_unconfirmed', 'lock_version' => DB::raw('lock_version + 1')]);
            throw $exception;
        }

        return DB::transaction(function () use ($asset, $id, $actor): VideoAsset {
            $asset = VideoAsset::query()->lockForUpdate()->findOrFail($asset->id);
            abort_unless($asset->provider_video_id === null, 409);
            $asset->forceFill(['provider_video_id' => $id, 'lock_version' => $asset->lock_version + 1])->save();
            $this->audits->log('lms_video_upload_created', VideoAsset::class, $asset->id, null, ['course_id' => $asset->course_id, 'status' => $asset->status], $actor->id);

            return $asset;
        }, 3);
    }

    /** @return array<string,string|int> */
    public function uploadAuthorization(Administrator $actor, Course $course, VideoAsset $asset): array
    {
        Gate::forUser($actor)->authorize('manage', $course);
        $asset = VideoAsset::query()->where('course_id', $course->id)->findOrFail($asset->id);
        abort_unless($asset->status === 'uploading' && $asset->provider_video_id !== null && (int) $asset->created_by === (int) $actor->id, 409, 'This upload is no longer open.');
        $connection = $asset->connection;
        abort_unless($connection !== null && (int) $connection->library_id === (int) $asset->library_id, 404);

        return $this->provider->uploadAuthorization($connection, $asset->provider_video_id, now('UTC')->addMinutes(15)->timestamp);
    }

    /** External providers carry no claim of Bunny protection.
     * @param array<string,mixed> $data */
    public function external(Administrator $actor, Course $course, array $data, int $version): VideoAsset
    {
        Gate::forUser($actor)->authorize('manage', $course);
        $values = Validator::make($data, ['provider' => ['required', 'in:youtube,external'], 'url' => ['required', 'string', 'max:2000'], 'label' => ['required', 'string', 'max:200'], 'request_key' => ['required', 'uuid']])->validate();
        $normalized = app(LmsContentService::class)->normalize($course, ['kind' => $values['provider'] === 'youtube' ? 'youtube_video' : 'external_video', 'url' => $values['url']]);

        return DB::transaction(function () use ($actor, $course, $values, $normalized, $version): VideoAsset {
            if ($course->kind === 'private') {
                $this->students->lockStudent($course->owner_student_id);
            }
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('manage', $course);
            abort_unless($course->lock_version === $version && $course->status !== 'archived', 409);
            $asset = new VideoAsset;
            $asset->forceFill(['course_id' => $course->id, 'provider' => $values['provider'], 'external_url' => $normalized['payload']['url'],
                'label' => $values['label'], 'status' => 'ready', 'created_by' => $actor->id, 'upload_request_key' => $values['request_key']])->save();
            $course->forceFill(['lock_version' => $course->lock_version + 1])->save();
            $this->audits->log('lms_video_external_attached', VideoAsset::class, $asset->id, null, ['course_id' => $course->id, 'provider' => $asset->provider], $actor->id);

            return $asset;
        }, 3);
    }

    public function reconcile(VideoAsset $asset): VideoAsset
    {
        $asset = VideoAsset::query()->findOrFail($asset->id);
        if ($asset->provider !== 'bunny' || $asset->provider_video_id === null || in_array($asset->status, ['withdrawn', 'deleting', 'deleted', 'delete_failed'], true)) {
            return $asset;
        }
        $connection = $asset->connection;
        if (! $connection || (int) $connection->library_id !== (int) $asset->library_id) {
            throw new VideoProviderUnavailable;
        }

        return DB::transaction(function () use ($asset, $connection): VideoAsset {
            $asset = VideoAsset::query()->lockForUpdate()->findOrFail($asset->id);
            if (in_array($asset->status, ['withdrawn', 'deleting', 'deleted', 'delete_failed'], true)) {
                return $asset;
            }
            // Serialize bounded reads so an older poll cannot overwrite a newer reconciliation.
            $metadata = $this->provider->video($connection, $asset->provider_video_id);
            $status = match ($metadata['status']) {
                4 => $metadata['duration'] > 0 && ! $metadata['has_mp4_fallback'] ? 'ready' : 'failed',
                5, 6 => 'failed',
                0 => 'uploading',
                default => 'processing',
            };
            $asset->forceFill(['status' => $status, 'provider_status' => $metadata['status'], 'duration_seconds' => $metadata['duration'],
                'has_mp4_fallback' => $metadata['has_mp4_fallback'], 'failure_code' => $status === 'failed' ? 'media_not_usable' : null,
                'reconciled_at' => now('UTC'), 'lock_version' => $asset->lock_version + 1])->save();

            return $asset;
        }, 3);
    }

    public function webhook(VideoProviderConnection $connection, string $rawBody, string $version, string $algorithm, string $signature): void
    {
        abort_unless($connection->enabled && $connection->read_only_key && strlen($rawBody) <= 4096
            && $version === 'v1' && $algorithm === 'hmac-sha256' && preg_match('/^[a-f0-9]{64}$/D', $signature)
            && hash_equals(hash_hmac('sha256', $rawBody, $connection->read_only_key), $signature), 401);
        try {
            $decoded = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(422, 'Invalid media callback.');
        }
        abort_unless(is_array($decoded), 422);
        $values = Validator::make($decoded, ['VideoLibraryId' => ['required', 'integer', 'min:1'], 'VideoGuid' => ['required', 'uuid'], 'Status' => ['required', 'integer', 'between:0,10']])->validate();
        abort_unless((int) $values['VideoLibraryId'] === (int) $connection->library_id, 404);
        $asset = VideoAsset::query()->where('provider_connection_id', $connection->id)->where('library_id', $connection->library_id)->where('provider_video_id', $values['VideoGuid'])->firstOrFail();
        $hash = hash('sha256', $rawBody);
        if (VideoWebhookReceipt::query()->where('receipt_hash', $hash)->exists()) {
            return;
        }
        $this->reconcile($asset);
        DB::table('lms_video_webhook_receipts')->insertOrIgnore(['video_asset_id' => $asset->id, 'receipt_hash' => $hash,
            'reported_status' => $values['Status'], 'received_at' => now('UTC'), 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
    }

    public function hasReferences(VideoAsset $asset): bool
    {
        if (LessonBlock::query()->where('video_asset_id', $asset->id)->exists()) {
            return true;
        }
        $referenced = false;
        ContentRevision::query()->where('revisable_type', Course::class)->select(['id', 'content'])->orderBy('id')->chunkById(1, function ($rows) use ($asset, &$referenced): bool {
            foreach ($rows as $revision) {
                foreach ($revision->content['sections'] ?? [] as $section) {
                    foreach ($section['lessons'] ?? [] as $lesson) {
                        foreach ($lesson['blocks'] ?? [] as $block) {
                            if ((int) ($block['video_asset_id'] ?? 0) === (int) $asset->id) {
                                $referenced = true;

                                return false;
                            }
                        }
                    }
                }
            }

            return true;
        });

        return $referenced;
    }

    public function deleteRemote(Administrator $actor, Course $course, VideoAsset $asset): void
    {
        Gate::forUser($actor)->authorize('manage', $course);
        $asset = DB::transaction(function () use ($actor, $course, $asset): VideoAsset {
            if ($course->kind === 'private') {
                $this->students->lockStudent($course->owner_student_id);
            }
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('manage', $course);
            $asset = VideoAsset::query()->where('course_id', $course->id)->lockForUpdate()->findOrFail($asset->id);
            abort_if($asset->provider === 'bunny' && $asset->provider_video_id === null, 409, 'Remote creation is unconfirmed. Review the provider library before deleting this attempt.');
            abort_if($this->hasReferences($asset), 409, 'This media is still referenced by a course or retained revision.');
            abort_if($asset->status === 'deleted', 409, 'This remote media is already deleted.');
            $asset->forceFill(['status' => 'deleting', 'lock_version' => $asset->lock_version + 1])->save();

            return $asset;
        }, 3);
        try {
            if ($asset->provider_video_id !== null) {
                $connection = $asset->connection;
                if (! $connection) {
                    throw new VideoProviderUnavailable;
                }
                $this->provider->delete($connection, $asset->provider_video_id);
            }
        } catch (VideoProviderUnavailable $exception) {
            $asset->forceFill(['status' => 'delete_failed', 'failure_code' => 'delete_unconfirmed'])->save();
            throw $exception;
        }
        $asset->forceFill(['status' => 'deleted', 'failure_code' => null, 'lock_version' => $asset->lock_version + 1])->save();
        $this->audits->log('lms_video_remote_deleted', VideoAsset::class, $asset->id, null, ['course_id' => $asset->course_id, 'status' => 'deleted'], $actor->id);
    }
}
