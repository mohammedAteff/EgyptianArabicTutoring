<?php

namespace App\Domains\Booking\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LmsAsset;
use App\Domains\Resources\Models\Resource;
use App\Rules\SafeLessonUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class LessonMaterialService
{
    public function __construct(private AuditLogService $audits) {}

    public function storeCourseAsset(Course $course, Administrator $actor, UploadedFile $file, string $kind): LmsAsset
    {
        Gate::forUser($actor)->authorize('manage', $course);
        Validator::make(['kind' => $kind, 'file' => $file], [
            'kind' => ['required', 'in:image,file'],
            'file' => $kind === 'image' ? ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=12000,max_height=12000']
                : ['required', 'file', 'mimes:pdf,zip,doc,docx,mp3,wav,m4a', 'max:51200'],
        ])->validate();
        $uploadPath = $file->getRealPath();
        if (! is_string($uploadPath)) {
            throw ValidationException::withMessages(['file' => 'The uploaded file could not be read. Please retry.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($uploadPath);
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            'application/pdf' => 'pdf', 'application/zip', 'application/x-zip-compressed' => 'zip',
            'application/msword', 'application/vnd.ms-office' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'audio/mpeg' => 'mp3', 'audio/wav', 'audio/x-wav', 'audio/vnd.wave' => 'wav', 'audio/mp4', 'audio/x-m4a' => 'm4a',
            default => throw ValidationException::withMessages(['file' => 'This file type is not supported.']),
        };
        $path = null;
        try {
            $path = $file->storeAs('lms-assets', Str::uuid().'.'.$extension, 'local');
            if (! is_string($path) || ! Storage::disk('local')->exists($path) || (int) $file->getSize() < 1) {
                throw ValidationException::withMessages(['file' => 'The attachment could not be saved. Please retry.']);
            }
            $asset = new LmsAsset;
            $asset->forceFill(['course_id' => $course->id, 'kind' => $kind, 'status' => 'active', 'disk' => 'local', 'path' => $path,
                'mime_type' => $mime, 'byte_size' => $file->getSize(), 'sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200), 'created_by' => $actor->id])->save();

            return $asset;
        } catch (Throwable $exception) {
            if (is_string($path)) {
                $this->discardCourseAssetFile($path);
            }
            throw $exception;
        }
    }

    public function courseAssetAvailable(LmsAsset $asset): bool
    {
        if ($asset->status !== 'active' || $asset->disk !== 'local' || ! is_string($asset->path)
            || ! preg_match('~^lms-assets/[0-9a-f-]{36}\.(?:jpg|png|webp|pdf|zip|doc|docx|mp3|wav|m4a)$~D', $asset->path)) {
            return false;
        }
        $path = $this->privatePath($asset->path, 'lms-assets');
        if ($path !== null && $asset->kind === 'image') {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $mime !== $asset->mime_type) {
                return false;
            }
        }

        return $path !== null && filesize($path) === $asset->byte_size && hash_equals($asset->sha256, (string) hash_file('sha256', $path));
    }

    public function openCourseAsset(LmsAsset $asset): BinaryFileResponse
    {
        abort_unless($this->courseAssetAvailable($asset), 404);
        abort_unless(is_string($asset->path), 404);
        $path = $this->privatePath($asset->path, 'lms-assets');
        abort_if($path === null, 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($asset->kind === 'image') {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && $mime === $asset->mime_type, 404);
            $response = response()->file($path, ['Content-Type' => $mime]);
        } else {
            $response = response()->download($path, 'course-attachment.'.$extension, ['Content-Type' => 'application/octet-stream']);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->setPrivate();

        return $response;
    }

    public function discardCourseAssetFile(string $path): bool
    {
        if (! preg_match('~^lms-assets/[0-9a-f-]{36}\.(?:jpg|png|webp|pdf|zip|doc|docx|mp3|wav|m4a)$~D', $path)) {
            return false;
        }

        return $this->privatePath($path, 'lms-assets') === null ? ! Storage::disk('local')->exists($path) : Storage::disk('local')->delete($path);
    }

    public function cleanupCourseAsset(LmsAsset $asset): bool
    {
        if ($asset->status !== 'withdrawn') {
            return false;
        }
        try {
            if ($asset->path === null) {
                return true;
            }
            if (! $this->discardCourseAssetFile($asset->path)) {
                return false;
            }
            $asset->forceFill(['path' => null, 'original_name' => null])->save();

            return true;
        } catch (Throwable) {
            Log::warning('Course attachment privacy cleanup pending.', ['asset_id' => $asset->id]);

            return false;
        }
    }

    public function resourceAvailable(Resource $resource): bool
    {
        if (! $resource->isPublished()) {
            return false;
        }
        if ($resource->external_url) {
            return SafeLessonUrl::isSafe($resource->external_url);
        }
        $path = $resource->file_path;

        return is_string($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['pdf', 'zip', 'doc', 'docx', 'mp3', 'wav', 'm4a'], true)
            && $this->privatePath($path, 'resources') !== null;
    }

    /** @param array<string, mixed> $data */
    public function attach(Booking $booking, array $data, int $actorId): LessonMaterial
    {
        $path = null;
        try {
            return DB::transaction(function () use ($booking, $data, $actorId, &$path): LessonMaterial {
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                if (! $locked->student()->whereNull('merged_into_student_id')->exists()) {
                    throw ValidationException::withMessages(['title' => 'This lesson needs an active student record before materials can be attached.']);
                }
                if ($data['kind'] === 'resource') {
                    Resource::query()->published()->lockForUpdate()->findOrFail((int) $data['resource_id']);
                }
                if ($data['kind'] === 'private_file') {
                    /** @var UploadedFile $file */
                    $file = $data['file'];
                    $stored = $file->storeAs('lesson-materials', Str::uuid().'.pdf', 'local');
                    if (! is_string($stored)) {
                        throw ValidationException::withMessages(['file' => 'The PDF could not be saved. Please retry.']);
                    }
                    $path = $stored;
                }
                $material = $locked->lessonMaterials()->create([
                    'created_by' => $actorId, 'kind' => $data['kind'], 'title' => $data['title'],
                    'description' => $data['description'] ?? null, 'student_visible' => $data['student_visible'],
                    'sort_order' => $data['sort_order'], 'resource_id' => $data['kind'] === 'resource' ? $data['resource_id'] : null,
                    'url' => in_array($data['kind'], ['external_link', 'recording'], true) ? $data['url'] : null,
                    'disk' => $path ? 'local' : null, 'path' => $path,
                ]);
                $this->audit($material, $material->kind === 'recording' ? 'lesson_recording_attached' : 'lesson_material_attached', $actorId);

                return $material;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Booking $booking, LessonMaterial $material, array $data, int $actorId): void
    {
        DB::transaction(function () use ($booking, $material, $data, $actorId): void {
            Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $locked = $this->lockedMaterial($booking, $material);
            abort_if($locked->withdrawn_at !== null, 404);
            $previous = $locked->only(['student_visible', 'sort_order']);
            $locked->update($data);
            $this->audit($locked, $previous['student_visible'] !== $locked->student_visible
                ? 'lesson_material_visibility_changed' : 'lesson_material_updated', $actorId, $previous);
        });
    }

    public function withdraw(Booking $booking, LessonMaterial $material, int $actorId): bool
    {
        $locked = DB::transaction(function () use ($booking, $material, $actorId): LessonMaterial {
            Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $locked = $this->lockedMaterial($booking, $material);
            if (! $locked->withdrawn_at) {
                $locked->update(['withdrawn_at' => now('UTC'), 'student_visible' => false, 'url' => null, 'resource_id' => null]);
                $this->audit($locked, $locked->kind === 'recording' ? 'lesson_recording_withdrawn' : 'lesson_material_withdrawn', $actorId);
            }

            return $locked;
        });

        return $this->cleanup($locked);
    }

    /** Booking locks are already held by the privacy service.
     * @param  list<int>  $bookingIds
     */
    public function eraseForBookings(array $bookingIds, ?int $actorId): void
    {
        LessonMaterial::query()->whereIn('booking_id', $bookingIds)->orderBy('id')->lockForUpdate()->get()
            ->each(function (LessonMaterial $material) use ($actorId): void {
                $material->update([
                    'title' => 'Redacted lesson material', 'description' => null, 'url' => null, 'resource_id' => null,
                    'student_visible' => false, 'withdrawn_at' => $material->withdrawn_at ?? now('UTC'),
                ]);
                $this->audit($material, 'lesson_material_privacy_erased', $actorId);
                DB::afterCommit(function () use ($material): void {
                    if (! $this->cleanup($material)) {
                        Log::warning('Lesson PDF privacy cleanup pending.', ['material_id' => $material->id]);
                    }
                });
            });
    }

    public function cleanup(LessonMaterial $material): bool
    {
        if (! $material->withdrawn_at || $material->path === null) {
            return true;
        }
        if ($material->disk !== 'local' || ! $this->isLessonPath($material->path)) {
            return false;
        }
        try {
            $file = $this->privatePath($material->path, 'lesson-materials');
            if ($file !== null && ! Storage::disk('local')->delete($material->path)) {
                return false;
            }
            if ($file === null && Storage::disk('local')->exists($material->path)) {
                return false;
            }
            $material->update(['disk' => null, 'path' => null]);

            return true;
        } catch (Throwable) {
            Log::warning('Lesson PDF cleanup pending.', ['material_id' => $material->id]);

            return false;
        }
    }

    public function open(LessonMaterial $material): BinaryFileResponse|RedirectResponse
    {
        if (in_array($material->kind, ['external_link', 'recording'], true)) {
            return $this->external($material->url);
        }
        $path = $material->path;
        $directory = 'lesson-materials';
        $extension = 'pdf';
        if ($material->kind === 'resource') {
            $resource = $material->resource;
            abort_unless($resource && $resource->isPublished(), 404);
            if ($resource->external_url) {
                return $this->external($resource->external_url);
            }
            $path = $resource->file_path;
            $directory = 'resources';
            $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
            abort_unless(in_array($extension, ['pdf', 'zip', 'doc', 'docx', 'mp3', 'wav', 'm4a'], true), 404);
        } else {
            abort_unless($material->disk === 'local' && is_string($path) && $this->isLessonPath($path), 404);
        }
        $file = is_string($path) ? $this->privatePath($path, $directory) : null;
        abort_if($file === null, 404);

        $response = response()->download($file, (Str::slug($material->title) ?: 'lesson-material').'.'.$extension, [
            'Content-Type' => $extension === 'pdf' ? 'application/pdf' : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
        $response->setPrivate();

        return $response;
    }

    public function openResource(Resource $resource): BinaryFileResponse|RedirectResponse
    {
        $reference = new LessonMaterial(['kind' => 'resource', 'title' => $resource->title]);
        $reference->setRelation('resource', $resource);

        return $this->open($reference);
    }

    private function external(?string $url): RedirectResponse
    {
        abort_unless(is_string($url) && SafeLessonUrl::isSafe($url), 404);

        return redirect()->away($url, 302, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    private function isLessonPath(string $path): bool
    {
        return preg_match('~\Alesson-materials/[0-9a-f-]{36}\.pdf\z~D', $path) === 1;
    }

    private function privatePath(string $path, string $directory): ?string
    {
        if (! str_starts_with($path, $directory.'/') || str_contains($path, '\\') || str_contains($path, "\0")
            || preg_match('~(?:^|/)\.\.?(?:/|$)~', $path)) {
            return null;
        }
        $root = realpath(Storage::disk('local')->path($directory));
        $file = realpath(Storage::disk('local')->path($path));
        if ($root === false || $file === false || ! is_file($file)) {
            return null;
        }
        $normalizedRoot = str_replace('\\', '/', $root).'/';
        $normalizedFile = str_replace('\\', '/', $file);

        return str_starts_with($normalizedFile, $normalizedRoot) ? $file : null;
    }

    /** @return array{path:string,mime_type:string,byte_size:int,sha256:string} */
    public function storeLearningSubmission(UploadedFile $file, string $kind): array
    {
        $allowed = match ($kind) {
            'file' => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'text/plain' => 'txt'],
            'audio' => ['audio/mpeg' => 'mp3', 'audio/x-wav' => 'wav', 'audio/wav' => 'wav', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'video/webm' => 'webm', 'audio/webm' => 'webm'],
            'video' => ['video/mp4' => 'mp4', 'video/webm' => 'webm'],
            default => [],
        };
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'extensions:'.implode(',', array_unique(array_values($allowed))), 'max:'.($kind === 'file' ? 10240 : ($kind === 'audio' ? 25600 : 51200))]])->validate();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset($allowed[$mime])) {
            throw ValidationException::withMessages(['file' => 'This file content type is not supported.']);
        }
        $path = $file->storeAs('lms-submissions', Str::uuid().'.'.$allowed[$mime], 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages(['file' => 'The private file could not be stored.']);
        }

        return ['path' => $path, 'mime_type' => $mime, 'byte_size' => (int) $file->getSize(), 'sha256' => hash_file('sha256', Storage::disk('local')->path($path))];
    }

    public function openLearningSubmission(string $path, string $sha256): BinaryFileResponse
    {
        $file = $this->privatePath($path, 'lms-submissions');
        abort_unless($file && hash_equals($sha256, hash_file('sha256', $file)), 404);

        $response = response()->download($file, 'learning-submission.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer',
        ]);
        $response->setPrivate();

        return $response;
    }

    public function discardLearningSubmission(string $path): bool
    {
        return $this->privatePath($path, 'lms-submissions') !== null && Storage::disk('local')->delete($path);
    }

    private function lockedMaterial(Booking $booking, LessonMaterial $material): LessonMaterial
    {
        return $booking->lessonMaterials()->whereKey($material->id)->lockForUpdate()->firstOrFail();
    }

    /** @param array<string, mixed>|null $previous */
    private function audit(LessonMaterial $material, string $action, ?int $actorId, ?array $previous = null): void
    {
        $this->audits->log($action, LessonMaterial::class, $material->id, $previous,
            ['booking_id' => $material->booking_id, 'kind' => $material->kind, 'student_visible' => $material->student_visible, 'sort_order' => $material->sort_order], $actorId);
    }
}
